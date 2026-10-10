<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function all_locations(?array $types = null): array
{
    $sql = 'SELECT * FROM locations WHERE is_active = 1';
    $params = [];
    if ($types !== null) {
        $types = array_values(array_filter($types, fn ($type) => is_string($type) && $type !== ''));
        if ($types) {
            $sql .= ' AND type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            $params = $types;
        }
    }
    $sql .= ' ORDER BY id';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function all_locations_with_inactive(): array
{
    return pdo()->query('SELECT * FROM locations ORDER BY is_active DESC, type, id DESC')->fetchAll();
}

function find_location_any(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM locations WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function validate_location_data(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $type = (string) ($data['type'] ?? 'branch');
    $allowedTypes = ['warehouse', 'branch', 'online'];
    if ($name === '') {
        throw new RuntimeException('اسم الفرع أو الموقع مطلوب.');
    }
    if (!in_array($type, $allowedTypes, true)) {
        throw new RuntimeException('نوع الموقع غير صحيح.');
    }

    return [
        'name' => $name,
        'type' => $type,
        'latitude' => trim((string) ($data['latitude'] ?? '')),
        'longitude' => trim((string) ($data['longitude'] ?? '')),
        'geo_radius_m' => max(1, (int) ($data['geo_radius_m'] ?? 100)),
    ];
}

function add_location(array $data, int $userId): void
{
    $data = validate_location_data($data);
    $stmt = pdo()->prepare('INSERT INTO locations (name, type, latitude, longitude, geo_radius_m, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->execute([
        $data['name'],
        $data['type'],
        $data['latitude'] !== '' ? (float) $data['latitude'] : null,
        $data['longitude'] !== '' ? (float) $data['longitude'] : null,
        $data['geo_radius_m'],
    ]);
    log_audit($userId, 'create', 'location', (int) pdo()->lastInsertId(), 'إضافة فرع/موقع جديد: ' . $data['name']);
}

function update_location_data(array $data, int $userId): void
{
    $id = (int) ($data['id'] ?? 0);
    $location = find_location_any($id);
    if (!$location) {
        throw new RuntimeException('الفرع أو الموقع غير موجود.');
    }

    $data = validate_location_data($data);
    $stmt = pdo()->prepare('UPDATE locations SET name = ?, type = ?, latitude = ?, longitude = ?, geo_radius_m = ? WHERE id = ?');
    $stmt->execute([
        $data['name'],
        $data['type'],
        $data['latitude'] !== '' ? (float) $data['latitude'] : null,
        $data['longitude'] !== '' ? (float) $data['longitude'] : null,
        $data['geo_radius_m'],
        $id,
    ]);
    log_audit($userId, 'update', 'location', $id, 'تعديل بيانات الفرع/الموقع: ' . $data['name']);
}

function set_location_active(int $id, bool $isActive, int $userId): void
{
    $location = find_location_any($id);
    if (!$location) {
        throw new RuntimeException('الفرع أو الموقع غير موجود.');
    }

    $stmt = pdo()->prepare('UPDATE locations SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive ? 1 : 0, $id]);
    log_audit($userId, $isActive ? 'activate' : 'deactivate', 'location', $id, ($isActive ? 'تفعيل' : 'تعطيل') . ' الفرع/الموقع: ' . $location['name']);
}

function sale_locations(): array
{
    return all_locations(['branch', 'warehouse']);
}

function stock_locations(): array
{
    return all_locations(['warehouse', 'branch']);
}

function attendance_locations(): array
{
    return all_locations(['warehouse', 'branch']);
}

function product_type_labels(): array
{
    return ['bottle' => 'زجاجة', 'perfume_gram' => 'عطر بالجرام', 'fixed' => 'منتج جاهز', 'recipe' => 'تركيبة'];
}

function product_type_label(?string $type): string
{
    $labels = product_type_labels();
    return $labels[$type ?? ''] ?? (string) $type;
}

function product_unit_label(?string $unit): string
{
    return $unit === 'gram' ? 'جرام' : 'قطعة';
}

function perfume_family_labels(): array
{
    return [
        'oriental' => 'شرقي',
        'french' => 'فرنسي',
        'niche' => 'نيش',
        'niche_liter' => 'الترا نيش',
        'unilateral' => 'احادي',
    ];
}

function perfume_family_label(?string $family): string
{
    $labels = perfume_family_labels();
    return $labels[$family ?? ''] ?? (string) $family;
}

function quality_grade_labels(): array
{
    return ['' => 'بدون', 'A' => 'A', 'A+' => 'A+', 'B' => 'B', 'X' => 'X'];
}
function all_products(?string $type = null): array
{
    $sql = "SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram
        FROM products p
        LEFT JOIN product_bottle_details b ON b.product_id = p.id
        LEFT JOIN product_perfume_details d ON d.product_id = p.id
        WHERE p.is_active = 1";
    $params = [];
    if ($type !== null) {
        $sql .= ' AND p.type = ?';
        $params[] = $type;
    }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function all_products_with_stock(?int $locationId = null, ?string $type = null, bool $onlyInitialized = false): array
{
    if ($locationId === null) {
        return all_products($type);
    }
    $sql = "SELECT p.*,
                b.size_ml,
                d.perfume_family, d.quality_grade, d.price_per_gram,
                COALESCE(ib.quantity, 0) AS branch_stock,
                CASE WHEN ib.id IS NOT NULL THEN 1 ELSE 0 END AS stock_initialized
            FROM products p
            LEFT JOIN product_bottle_details b ON b.product_id = p.id
            LEFT JOIN product_perfume_details d ON d.product_id = p.id
            LEFT JOIN inventory_balances ib ON ib.product_id = p.id AND ib.location_id = ?
            WHERE p.is_active = 1";
    $params = [$locationId];
    if ($type !== null) {
        $sql .= ' AND p.type = ?';
        $params[] = $type;
    }
    if ($onlyInitialized) {
        $sql .= ' AND ib.id IS NOT NULL';
    }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function product_rows(array $filters = []): array
{
    $sql = "SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram
        FROM products p
        LEFT JOIN product_bottle_details b ON b.product_id = p.id
        LEFT JOIN product_perfume_details d ON d.product_id = p.id
        WHERE p.is_active = 1";
    $params = [];
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.barcode LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['type'])) {
        $sql .= ' AND p.type = ?';
        $params[] = (string) $filters['type'];
    }
    if (!empty($filters['unit'])) {
        $sql .= ' AND p.unit = ?';
        $params[] = (string) $filters['unit'];
    }
    if (!empty($filters['perfume_family'])) {
        $sql .= ' AND d.perfume_family = ?';
        $params[] = (string) $filters['perfume_family'];
    }
    if (isset($filters['quality_grade']) && $filters['quality_grade'] !== '') {
        $sql .= " AND COALESCE(d.quality_grade, '') = ?";
        $params[] = (string) $filters['quality_grade'];
    }
    if (!empty($filters['size_ml'])) {
        $sql .= ' AND b.size_ml = ?';
        $params[] = (int) $filters['size_ml'];
    }
    if (($filters['barcode_status'] ?? '') === 'with_barcode') {
        $sql .= " AND p.barcode IS NOT NULL AND p.barcode <> ''";
    } elseif (($filters['barcode_status'] ?? '') === 'without_barcode') {
        $sql .= " AND (p.barcode IS NULL OR p.barcode = '')";
    }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_product(int $id): ?array
{
    $stmt = pdo()->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram
        FROM products p
        LEFT JOIN product_bottle_details b ON b.product_id = p.id
        LEFT JOIN product_perfume_details d ON d.product_id = p.id
        WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function find_product_by_unique(?string $sku, ?string $barcode, ?int $excludeId = null): ?array
{
    $conditions = [];
    $params = [];

    if ($sku !== null && $sku !== '') {
        $conditions[] = 'p.sku = ?';
        $params[] = $sku;
    }
    if ($barcode !== null && $barcode !== '') {
        $conditions[] = 'p.barcode = ?';
        $params[] = $barcode;
    }
    if (!$conditions) {
        return null;
    }

    $sql = "SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram
        FROM products p
        LEFT JOIN product_bottle_details b ON b.product_id = p.id
        LEFT JOIN product_perfume_details d ON d.product_id = p.id
        WHERE (" . implode(' OR ', $conditions) . ')';
    if ($excludeId !== null) {
        $sql .= ' AND p.id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY p.is_active DESC, p.id DESC LIMIT 1';

    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function find_product_by_barcode(string $barcode): ?array
{
    $stmt = pdo()->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram
        FROM products p
        LEFT JOIN product_bottle_details b ON b.product_id = p.id
        LEFT JOIN product_perfume_details d ON d.product_id = p.id
        WHERE p.barcode = ? AND p.is_active = 1
        LIMIT 1");
    $stmt->execute([$barcode]);
    return $stmt->fetch() ?: null;
}

function save_product_details(PDO $db, int $productId, string $type, array $data): void
{
    if ($type === 'bottle' || ($type === 'fixed' && isset($data['size_ml']) && (int)$data['size_ml'] > 0)) {
        $stmt = $db->prepare('INSERT INTO product_bottle_details (product_id, size_ml) VALUES (?, ?) ON DUPLICATE KEY UPDATE size_ml = VALUES(size_ml)');
        $stmt->execute([$productId, (int) $data['size_ml']]);
        $db->prepare('DELETE FROM product_perfume_details WHERE product_id = ?')->execute([$productId]);
        return;
    }

    if ($type === 'perfume_gram') {
        $stmt = $db->prepare('INSERT INTO product_perfume_details (product_id, perfume_family, quality_grade, price_per_gram) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE perfume_family = VALUES(perfume_family), quality_grade = VALUES(quality_grade), price_per_gram = VALUES(price_per_gram)');
        $stmt->execute([$productId, $data['perfume_family'], $data['quality_grade'] ?: null, (float) $data['price_per_gram']]);
        $db->prepare('DELETE FROM product_bottle_details WHERE product_id = ?')->execute([$productId]);
        return;
    }

    $db->prepare('DELETE FROM product_bottle_details WHERE product_id = ?')->execute([$productId]);
    $db->prepare('DELETE FROM product_perfume_details WHERE product_id = ?')->execute([$productId]);
}

function reactivate_product(array $product, array $data): void
{
    if ($product['type'] !== $data['type']) {
        throw new RuntimeException('يوجد منتج محذوف بنفس الباركود لكن بنوع مختلف. عدل المنتج القديم أو استخدم رمزاً مختلفاً.');
    }

    $db = pdo();
    $type = $data['type'];
    $unit = $type === 'perfume_gram' ? 'gram' : 'unit';
    $salePrice = $type === 'perfume_gram' ? (float) $data['price_per_gram'] : (float) $data['sale_price'];
    $stmt = $db->prepare('UPDATE products SET sku = NULL, barcode = ?, name = ?, unit = ?, sale_price = ?, cost_price = ?, min_stock = ?, is_active = 1 WHERE id = ?');
    $stmt->execute([
        $data['barcode'] ?: $product['barcode'],
        $data['name'],
        $unit,
        $salePrice,
        $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
        (float) ($data['min_stock'] ?? 0),
        (int) $product['id'],
    ]);
    save_product_details($db, (int) $product['id'], $type, $data);

    $user = current_user();
    log_audit($user ? (int)$user['id'] : null, 'update', 'product', (int)$product['id'], 'إعادة تفعيل المنتج: ' . $data['name']);
}

function normalize_sizes_list(array|string|null $raw): array
{
    if (is_array($raw)) {
        $values = $raw;
    } else {
        $values = preg_split('/[\n,،]+/', (string) $raw) ?: [];
    }
    $sizes = [];
    foreach ($values as $v) {
        $size = (int) trim((string) $v);
        if ($size > 0) {
            $sizes[$size] = $size;
        }
    }
    return array_values($sizes);
}

function add_products_batch(array $data): array
{
    $type = (string) ($data['type'] ?? '');
    $baseName = trim((string) ($data['name'] ?? ''));
    if ($baseName === '') {
        throw new RuntimeException('اسم المنتج مطلوب.');
    }

    $variants = [];

    if ($type === 'bottle' || $type === 'fixed') {
        // New row-based input: variants[size][], variants[sale_price][], variants[cost_price][]
        $variantRows = $data['variants'] ?? [];
        $sizes = $variantRows['size'] ?? [];
        $barcodes = $variantRows['barcode'] ?? [];
        if (!is_array($sizes)) {
            $sizes = [];
        }
        if (!is_array($barcodes)) {
            $barcodes = [];
        }
        $requestBarcodes = [];
        foreach ($sizes as $idx => $rawSize) {
            $size = (int) $rawSize;
            if ($size <= 0) {
                continue;
            }
            $salePrice = (int) ($variantRows['sale_price'][$idx] ?? 0);
            $costPrice = $variantRows['cost_price'][$idx] ?? '';
            if ($salePrice < 0) {
                throw new RuntimeException('سعر البيع لا يمكن أن يكون بالسالب.');
            }
            $barcode = trim((string) ($barcodes[$idx] ?? ''));
            if ($barcode !== '') {
                if (isset($requestBarcodes[$barcode])) {
                    throw new RuntimeException('الباركود مكرر داخل نفس الطلب: ' . $barcode);
                }
                $requestBarcodes[$barcode] = true;

                $existing = find_product_by_unique(null, $barcode);
                if ($existing) {
                    if ((int) $existing['is_active'] === 1) {
                        throw new RuntimeException('الباركود مستخدم بالفعل في منتج نشط آخر: ' . $existing['name']);
                    }
                    throw new RuntimeException('الباركود مستخدم بالفعل في منتج محذوف أو غير نشط: ' . $existing['name']);
                }
            }
            $variant = $data;
            $variant['name'] = $baseName . ' ' . $size . 'ml';
            $variant['size_ml'] = $size;
            $variant['sale_price'] = $salePrice;
            $variant['cost_price'] = $costPrice;
            $variant['barcode'] = $barcode;
            $variants[] = $variant;
        }
        if (!$variants) {
            throw new RuntimeException('يجب إدخال حجم واحد على الأقل.');
        }
    } elseif ($type === 'perfume_gram') {
        $family = (string)($data['perfume_family'] ?? 'oriental');
        if (!array_key_exists($family, perfume_family_labels())) {
            throw new RuntimeException('عائلة العطر غير صحيحة.');
        }
        // New row-based input: variants[quality][], variants[price_per_gram][], variants[cost_price][]
        $variantRows = $data['variants'] ?? [];
        $qualities = $variantRows['quality'] ?? [];
        if (!is_array($qualities)) {
            $qualities = [];
        }
        $allowed = array_keys(quality_grade_labels());
        foreach ($qualities as $idx => $qualityRaw) {
            $quality = trim((string) $qualityRaw);
            if (!in_array($quality, $allowed, true)) {
                continue;
            }
            $pricePerGram = (int) ($variantRows['price_per_gram'][$idx] ?? 0);
            $costPrice = $variantRows['cost_price'][$idx] ?? '';
            if ($pricePerGram < 0) {
                throw new RuntimeException('سعر الجرام لا يمكن أن يكون بالسالب.');
            }
            $variant = $data;
            $variant['name'] = $baseName . ' ' . perfume_family_label($family) . ' ' . ($quality !== '' ? $quality : 'بدون كوتة');
            $variant['perfume_family'] = $family;
            $variant['quality_grade'] = $quality;
            $variant['price_per_gram'] = $pricePerGram;
            $variant['cost_price'] = $costPrice;
            $variant['barcode'] = '';
            $variants[] = $variant;
        }
        if (!$variants) {
            throw new RuntimeException('يجب إدخال كوتة واحدة على الأقل.');
        }
    } else {
        throw new RuntimeException('نوع المنتج غير صحيح.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        $ids = [];
        foreach ($variants as $variant) {
            $vType = (string) $variant['type'];
            $barcode = trim((string) ($variant['barcode'] ?? ''));
            if ($barcode === '') {
                $barcode = generate_unique_ean13($db);
            }
            $unit = $vType === 'perfume_gram' ? 'gram' : 'unit';
            $salePrice = $vType === 'perfume_gram' ? (float) ($variant['price_per_gram'] ?? 0) : (float) ($variant['sale_price'] ?? 0);
            $stmt = $db->prepare('INSERT INTO products (sku, barcode, name, type, unit, sale_price, cost_price, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                null,
                $barcode,
                (string) $variant['name'],
                $vType,
                $unit,
                $salePrice,
                isset($variant['cost_price']) && $variant['cost_price'] !== '' ? (float) $variant['cost_price'] : null,
                (float) ($variant['min_stock'] ?? 0),
            ]);
            $productId = (int) $db->lastInsertId();
            save_product_details($db, $productId, $vType, $variant);
            $ids[] = $productId;
        }
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'create', 'product', null, 'إضافة منتجات متعددة: ' . $baseName . ' (' . count($ids) . ')');
        $db->commit();
        return $ids;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function add_product(array $data): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $type = $data['type'];
        $barcode = trim((string) ($data['barcode'] ?? ''));
        $existing = find_product_by_unique(null, $barcode !== '' ? $barcode : null);
        if ($existing) {
            if ((int) $existing['is_active'] === 0) {
                reactivate_product($existing, $data);
                $db->commit();
                return;
            }
            throw new RuntimeException('الباركود مستخدم بالفعل في منتج آخر: ' . $existing['name']);
        }

        $unit = $type === 'perfume_gram' ? 'gram' : 'unit';
        $salePrice = $type === 'perfume_gram' ? (float) $data['price_per_gram'] : (float) $data['sale_price'];
        $stmt = $db->prepare('INSERT INTO products (sku, barcode, name, type, unit, sale_price, cost_price, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            null,
            $barcode !== '' ? $barcode : generate_unique_ean13($db),
            $data['name'],
            $type,
            $unit,
            $salePrice,
        isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            (float) ($data['min_stock'] ?? 0),
        ]);
        $productId = (int) $db->lastInsertId();
        save_product_details($db, $productId, $type, $data);
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'create', 'product', $productId, 'إضافة منتج جديد: ' . $data['name']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_product(array $data): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $product = find_product((int) $data['id']);
        if (!$product) {
            throw new RuntimeException('المنتج غير موجود.');
        }

        // Allow type change (from product_edit form)
        $allowedTypes = ['bottle', 'perfume_gram', 'fixed', 'recipe'];
        $newType = isset($data['type']) && in_array($data['type'], $allowedTypes, true)
            ? $data['type']
            : $product['type'];

        $barcode = trim((string) ($data['barcode'] ?? ''));
        $existing = find_product_by_unique(null, $barcode !== '' ? $barcode : null, (int) $data['id']);
        if ($existing) {
            throw new RuntimeException('الباركود مستخدم بالفعل في منتج آخر: ' . $existing['name']);
        }

        // Resolve cost_price and min_stock (product_edit uses aliased names for perfume_gram)
        $costPrice = null;
        $minStock  = 0.0;
        if ($newType === 'perfume_gram') {
            $rawCost = $data['cost_price_gram'] ?? $data['cost_price'] ?? '';
            $costPrice = $rawCost !== '' ? (float) $rawCost : null;
            $minStock  = (float) ($data['min_stock_gram'] ?? $data['min_stock'] ?? 0);
        } else {
            $rawCost = $data['cost_price'] ?? '';
            $costPrice = (isset($data['cost_price']) && $data['cost_price'] !== '') ? (float) $data['cost_price'] : null;
            $minStock  = (float) ($data['min_stock'] ?? 0);
        }

        $unit      = $newType === 'perfume_gram' ? 'gram' : 'unit';
        $salePrice = $newType === 'perfume_gram' ? (float) ($data['price_per_gram'] ?? 0) : (float) ($data['sale_price'] ?? 0);

        $stmt = $db->prepare('UPDATE products SET sku = NULL, barcode = ?, name = ?, type = ?, unit = ?, sale_price = ?, cost_price = ?, min_stock = ? WHERE id = ?');
        $stmt->execute([
            $barcode !== '' ? $barcode : $product['barcode'],
            $data['name'],
            $newType,
            $unit,
            $salePrice,
            $costPrice,
            $minStock,
            (int) $data['id'],
        ]);
        save_product_details($db, (int) $data['id'], $newType, $data);
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'update', 'product', (int)$data['id'], 'تعديل بيانات المنتج: ' . $data['name']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function settings_rows(): array
{
    return pdo()->query('SELECT * FROM app_settings ORDER BY setting_key')->fetchAll();
}

function payment_destination_label(string $method): ?string
{
    if ($method === 'instapay') {
        $value = trim((string) setting_value('payment_instapay_account', ''));
        return $value !== '' ? $value : null;
    }
    if ($method === 'vodafone_cash') {
        $value = trim((string) setting_value('payment_vodafone_number', ''));
        return $value !== '' ? $value : null;
    }
    return null;
}

function branch_treasury_balances(?int $locationId = null): array
{
    $db = pdo();
    $methods = ['cash', 'instapay', 'vodafone_cash'];
    $result = [];

    foreach ($methods as $method) {
        $result[$method] = [
            'balance' => 0.0,
            'today_in' => 0.0,
            'in' => 0.0,
            'out' => 0.0,
        ];
    }

    $applyTotal = static function (array &$result, string $method, float $total, string $direction, bool $affectBalance = true): void {
        if (!isset($result[$method])) {
            return;
        }

        $result[$method][$direction] += $total;
        if ($affectBalance) {
            $result[$method]['balance'] += $direction === 'in' ? $total : -$total;
        }
    };

    $locationWhere = $locationId !== null ? ' AND i.location_id = ?' : '';
    $locationParams = $locationId !== null ? [$locationId] : [];

    $stmt = $db->prepare('SELECT p.method, COALESCE(SUM(p.amount), 0) AS total
                          FROM payments p
                          JOIN invoices i ON i.id = p.invoice_id
                          WHERE p.method IN ("cash", "instapay", "vodafone_cash")' . $locationWhere . '
                          GROUP BY p.method');
    $stmt->execute($locationParams);
    foreach ($stmt->fetchAll() as $row) {
        $method = (string) $row['method'];
        if ($method === 'cash') {
            $applyTotal($result, $method, (float) $row['total'], 'in');
        } else {
            $result[$method]['in'] += (float) $row['total'];
        }
    }

    $stmt = $db->prepare('SELECT p.method, COALESCE(SUM(p.amount), 0) AS total
                          FROM payments p
                          JOIN invoices i ON i.id = p.invoice_id
                          WHERE p.method IN ("cash", "instapay", "vodafone_cash")
                            AND DATE(p.created_at) = CURDATE()' . $locationWhere . '
                          GROUP BY p.method');
    $stmt->execute($locationParams);
    foreach ($stmt->fetchAll() as $row) {
        $method = (string) $row['method'];
        $total = (float) $row['total'];
        $result[$method]['today_in'] = $total;
        if ($method !== 'cash') {
            $result[$method]['balance'] += $total;
        }
    }

    $stmt = $db->prepare('SELECT r.refund_method AS method, COALESCE(SUM(r.amount), 0) AS total
                          FROM return_invoices r
                          JOIN invoices i ON i.id = r.original_invoice_id
                          WHERE r.refund_method IN ("cash", "instapay", "vodafone_cash")' . $locationWhere . '
                          GROUP BY r.refund_method');
    $stmt->execute($locationParams);
    foreach ($stmt->fetchAll() as $row) {
        $method = (string) $row['method'];
        if ($method === 'cash') {
            $applyTotal($result, $method, (float) $row['total'], 'out');
        }
    }

    $stmt = $db->prepare('SELECT r.refund_method AS method, COALESCE(SUM(r.amount), 0) AS total
                          FROM return_invoices r
                          JOIN invoices i ON i.id = r.original_invoice_id
                          WHERE r.refund_method IN ("instapay", "vodafone_cash")
                            AND DATE(r.created_at) = CURDATE()' . $locationWhere . '
                          GROUP BY r.refund_method');
    $stmt->execute($locationParams);
    foreach ($stmt->fetchAll() as $row) {
        $method = (string) $row['method'];
        if (isset($result[$method])) {
            $result[$method]['balance'] -= (float) $row['total'];
        }
    }

    $transferSql = 'SELECT method, COALESCE(SUM(amount), 0) AS total
                    FROM branch_cash_transfers
                    WHERE status IN ("pending", "received") AND method = "cash"';
    $transferParams = [];
    if ($locationId !== null) {
        $transferSql .= ' AND location_id = ?';
        $transferParams[] = $locationId;
    }
    $transferSql .= ' GROUP BY method';
    $stmt = $db->prepare($transferSql);
    $stmt->execute($transferParams);
    foreach ($stmt->fetchAll() as $row) {
        $applyTotal($result, (string) $row['method'], (float) $row['total'], 'out');
    }

    if ($locationId !== null) {
        try {
            $stmt = $db->prepare('SELECT payment_method, COALESCE(SUM(amount), 0) AS total FROM expenses WHERE location_id = ? GROUP BY payment_method');
            $stmt->execute([$locationId]);
            foreach ($stmt->fetchAll() as $row) {
                $method = (string) ($row['payment_method'] ?? 'cash');
                $total = (float) $row['total'];
                $applyTotal($result, $method, $total, 'out');
            }
        } catch (Throwable $e) {
            try {
                $stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE location_id = ?');
                $stmt->execute([$locationId]);
                $total = (float) $stmt->fetchColumn();
                $applyTotal($result, 'cash', $total, 'out');
            } catch (Throwable $ex) {}
        }
    }

    return $result;
}



function update_settings(array $data, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        foreach (($data['settings'] ?? []) as $key => $value) {
            $stmt = $db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $stmt->execute([$key, trim((string) $value)]);
        }
        $db->commit();
        log_audit($userId, 'update', 'settings', null, 'تعديل إعدادات النظام');
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function setting_value(string $key, mixed $default = null): mixed
{
    $stmt = pdo()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function save_setting(string $key, string $value): void
{
    $stmt = pdo()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, trim($value)]);
}

function deactivate_product(int $id): void
{
    $stmt = pdo()->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
    $stmt->execute([$id]);
}

function delete_product(int $id): string
{
    $db = pdo();
    $product = find_product($id);
    if (!$product) {
        throw new RuntimeException('المنتج غير موجود.');
    }
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $db->commit();
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'delete', 'product', $id, 'حذف المنتج نهائياً: ' . $product['name']);
        return 'permanently_deleted';
    } catch (Throwable $e) {
        $db->rollBack();
        deactivate_product($id);
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'deactivate', 'product', $id, 'تعطيل وإخفاء المنتج: ' . $product['name']);
        return 'deactivated';
    }
}

function generate_ean13(): string
{
    $base = '622' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $base . $check;
}

function generate_unique_ean13(PDO $db): string
{
    for ($i = 0; $i < 50; $i++) {
        $barcode = generate_ean13();
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE barcode = ?');
        $stmt->execute([$barcode]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $barcode;
        }
    }
    throw new RuntimeException('تعذر توليد باركود فريد. حاول مرة أخرى.');
}

function validate_supply_transfer(array $data): void
{
    $from = (int) $data['from_location_id'];
    $to = (int) $data['to_location_id'];
    require_location_access($from);
    require_location_type($from, ['warehouse'], 'أمر التوريد يجب أن يكون من المخزن الرئيسي فقط.');
    require_location_type($to, ['branch'], 'أمر التوريد يجب أن يكون إلى فرع فقط.');
    if ($from === $to) {
        throw new RuntimeException('لا يمكن التحويل لنفس الموقع.');
    }
}

function validate_branch_transfer(array $data): void
{
    $from = (int) $data['from_location_id'];
    $to = (int) $data['to_location_id'];
    require_location_access($from);
    require_location_type($from, ['branch'], 'التحويل بين الفروع يجب أن يكون من فرع.');
    require_location_type($to, ['branch'], 'التحويل بين الفروع يجب أن يكون إلى فرع.');
    if ($from === $to) {
        throw new RuntimeException('لا يمكن التحويل لنفس الموقع.');
    }
}

function require_transfer_location_types(PDO $db, array $transfer, array $fromTypes, array $toTypes): void
{
    $stmt = $db->prepare('SELECT fl.type AS from_type, tl.type AS to_type FROM locations fl JOIN locations tl ON tl.id = ? WHERE fl.id = ?');
    $stmt->execute([(int) $transfer['to_location_id'], (int) $transfer['from_location_id']]);
    $types = $stmt->fetch();
    if (!$types || !in_array($types['from_type'], $fromTypes, true) || !in_array($types['to_type'], $toTypes, true)) {
        throw new RuntimeException('نوع أمر التحويل لا يطابق الصفحة الحالية.');
    }
}

function transfer_line_items(array $data): array
{
    $items = [];
    foreach (($data['product_id'] ?? []) as $idx => $productId) {
        $productId = (int) $productId;
        $quantity = (float) ($data['quantity'][$idx] ?? 0);
        if ($productId > 0 && $quantity > 0) {
            $items[] = ['product_id' => $productId, 'quantity' => $quantity];
        }
    }
    return $items;
}

function assert_transfer_stock_available(PDO $db, int $fromLocationId, array $lineItems): void
{
    if (!$lineItems) {
        throw new RuntimeException('يجب إضافة صنف واحد على الأقل في التحويل.');
    }

    // Aggregate duplicate product rows to validate total requested quantity.
    $required = [];
    foreach ($lineItems as $item) {
        $productId = (int) $item['product_id'];
        $required[$productId] = ($required[$productId] ?? 0) + (float) $item['quantity'];
    }

    $stockStmt = $db->prepare('SELECT COALESCE(quantity, 0) FROM inventory_balances WHERE location_id = ? AND product_id = ? FOR UPDATE');
    foreach ($required as $productId => $quantity) {
        $stockStmt->execute([$fromLocationId, $productId]);
        $available = (float) ($stockStmt->fetchColumn() ?: 0);
        if ($available + 0.0001 < $quantity) {
            $product = find_product((int) $productId);
            throw new RuntimeException('لا يمكن تنفيذ التحويل. الرصيد غير كاف للصنف: ' . ($product['name'] ?? ('#' . $productId)) . ' — المتاح: ' . qty($available) . ' والمطلوب: ' . qty($quantity));
        }
    }
}

function inventory_rows(?int $locationId = null): array
{
    $sql = "SELECT 
                ib.id,
                l.id AS location_id,
                l.name AS location_name,
                p.id AS product_id,
                p.name AS product_name,
                p.type,
                p.unit,
                p.min_stock,
                COALESCE(ib.quantity, 0) AS quantity
            FROM locations l
            CROSS JOIN products p
            LEFT JOIN inventory_balances ib ON ib.location_id = l.id AND ib.product_id = p.id
            WHERE p.is_active = 1 AND l.is_active = 1";
    $params = [];
    if ($locationId) {
        $sql .= ' AND l.id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY l.id, p.name';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function inventory_movements_rows(int $limit = 100, ?int $locationId = null): array
{
    $sql = 'SELECT im.*, l.name AS location_name, p.name AS product_name, u.name AS user_name 
            FROM inventory_movements im 
            JOIN locations l ON l.id = im.location_id 
            JOIN products p ON p.id = im.product_id 
            JOIN users u ON u.id = im.created_by';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE im.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY im.created_at DESC, im.id DESC LIMIT ' . (int) $limit;
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function inventory_addition_rows(array $filters = [], ?int $locationId = null): array
{
    $sql = 'SELECT im.*, l.name AS location_name, p.name AS product_name, p.sale_price, p.cost_price, p.unit, u.name AS user_name 
            FROM inventory_movements im 
            JOIN locations l ON l.id = im.location_id 
            JOIN products p ON p.id = im.product_id 
            JOIN users u ON u.id = im.created_by 
            WHERE im.quantity_delta > 0 AND im.movement_type IN ("initial","manual_adjustment")';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' AND im.location_id = ?';
        $params[] = $locationId;
    }
    if (!empty($filters['location_id'])) {
        $sql .= ' AND im.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }
    if (!empty($filters['product_id'])) {
        $sql .= ' AND im.product_id = ?';
        $params[] = (int) $filters['product_id'];
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (p.name LIKE ? OR im.notes LIKE ? OR l.name LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND DATE(im.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND DATE(im.created_at) <= ?';
        $params[] = $filters['date_to'];
    }
    $sql .= ' ORDER BY im.created_at DESC, im.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_inventory_movement(int $movementId): ?array
{
    $stmt = pdo()->prepare('SELECT im.*, p.sale_price, p.cost_price FROM inventory_movements im JOIN products p ON p.id = im.product_id WHERE im.id = ?');
    $stmt->execute([$movementId]);
    return $stmt->fetch() ?: null;
}

function update_product_prices(int $productId, float $salePrice, ?float $costPrice): void
{
    $stmt = pdo()->prepare('UPDATE products SET sale_price = ?, cost_price = ? WHERE id = ?');
    $stmt->execute([$salePrice, $costPrice, $productId]);
}

function create_inventory_addition(array $data, int $userId): void
{
    $locationId = (int) ($data['location_id'] ?? 0);
    $productId = (int) ($data['product_id'] ?? 0);
    $quantity = (float) ($data['quantity'] ?? 0);
    $notes = trim((string) ($data['notes'] ?? 'إضافة مخزون'));
    $salePrice = (float) ($data['sale_price'] ?? 0);
    $costPrice = $data['cost_price'] !== '' ? (float) $data['cost_price'] : null;

    if ($locationId <= 0 || $productId <= 0 || $quantity <= 0) {
        throw new RuntimeException('الرجاء إدخال موقع، صنف، وكمية صالحة.');
    }

    update_product_prices($productId, $salePrice, $costPrice);

    $db = pdo();
    $db->beginTransaction();
    try {
        move_inventory($db, $locationId, $productId, $quantity, 'initial', $userId, 'manual', null, $notes);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function create_inventory_additions(array $data, int $userId): void
{
    $locationId = (int) ($data['location_id'] ?? 0);
    if ($locationId <= 0) {
        throw new RuntimeException('الرجاء تحديد موقع صالح.');
    }

    $productIds = array_values(array_filter((array) ($data['product_id'] ?? []), fn ($id) => is_numeric($id) && (int) $id > 0));
    $quantities = array_values((array) ($data['quantity'] ?? []));
    $salePrices = array_values((array) ($data['sale_price'] ?? []));
    $costPrices = array_values((array) ($data['cost_price'] ?? []));
    $notesList = array_values((array) ($data['notes'] ?? []));

    if (empty($productIds)) {
        throw new RuntimeException('يرجى إضافة صنف واحد على الأقل.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $quantity = (float) ($quantities[$index] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $salePrice = isset($salePrices[$index]) ? (float) $salePrices[$index] : 0;
            $costPrice = isset($costPrices[$index]) && $costPrices[$index] !== '' ? (float) $costPrices[$index] : null;
            $notes = trim((string) ($notesList[$index] ?? 'إضافة مخزون'));
            if ($notes === '') {
                $notes = 'إضافة مخزون';
            }

            update_product_prices($productId, $salePrice, $costPrice);
            move_inventory($db, $locationId, $productId, $quantity, 'initial', $userId, 'manual', null, $notes);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_inventory_addition(array $data, int $userId): void
{
    $movementId = (int) ($data['movement_id'] ?? 0);
    $movement = find_inventory_movement($movementId);
    if (!$movement) {
        throw new RuntimeException('حركة الإضافة غير موجودة.');
    }
    $locationId = (int) ($data['location_id'] ?? $movement['location_id']);
    $productId = (int) ($data['product_id'] ?? $movement['product_id']);
    $quantity = (float) ($data['quantity'] ?? 0);
    $notes = trim((string) ($data['notes'] ?? 'تعديل إضافة مخزون'));
    $salePrice = (float) ($data['sale_price'] ?? 0);
    $costPrice = $data['cost_price'] !== '' ? (float) $data['cost_price'] : null;

    if ($locationId <= 0 || $productId <= 0 || $quantity <= 0) {
        throw new RuntimeException('الرجاء إدخال موقع، صنف، وكمية صالحة.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        $oldQuantity = (float) $movement['quantity_delta'];
        if ($oldQuantity !== 0.0) {
            move_inventory($db, (int) $movement['location_id'], (int) $movement['product_id'], -1 * $oldQuantity, 'manual_adjustment', $userId, 'manual', null, 'إلغاء إضافة مخزون #' . $movementId);
        }
        $stmt = $db->prepare('DELETE FROM inventory_movements WHERE id = ?');
        $stmt->execute([$movementId]);

        update_product_prices($productId, $salePrice, $costPrice);
        move_inventory($db, $locationId, $productId, $quantity, 'initial', $userId, 'manual', null, $notes);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_inventory_addition(int $movementId, int $userId): void
{
    $movement = find_inventory_movement($movementId);
    if (!$movement) {
        throw new RuntimeException('حركة الإضافة غير موجودة.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        $delta = -1 * (float) $movement['quantity_delta'];
        if ($delta !== 0.0) {
            move_inventory($db, (int) $movement['location_id'], (int) $movement['product_id'], $delta, 'manual_adjustment', $userId, 'manual', null, 'حذف إضافة مخزون #' . $movementId);
        }
        $stmt = $db->prepare('DELETE FROM inventory_movements WHERE id = ?');
        $stmt->execute([$movementId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_stock(int $locationId, int $productId): float
{
    $stmt = pdo()->prepare('SELECT quantity FROM inventory_balances WHERE location_id = ? AND product_id = ?');
    $stmt->execute([$locationId, $productId]);
    return (float) ($stmt->fetchColumn() ?: 0);
}

function move_inventory(PDO $db, int $locationId, int $productId, float $delta, string $type, int $userId, ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): void
{
    $stmt = $db->prepare('SELECT quantity FROM inventory_balances WHERE location_id = ? AND product_id = ? FOR UPDATE');
    $stmt->execute([$locationId, $productId]);
    $current = $stmt->fetchColumn();
    $newQuantity = (float) ($current ?: 0) + $delta;
    if ($newQuantity < -0.0001) {
        $product = find_product($productId);
        throw new RuntimeException('المخزون غير كاف للصنف: ' . ($product['name'] ?? ('#' . $productId)));
    }

    if ($current === false) {
        $stmt = $db->prepare('INSERT INTO inventory_balances (location_id, product_id, quantity) VALUES (?, ?, ?)');
        $stmt->execute([$locationId, $productId, $newQuantity]);
    } else {
        $stmt = $db->prepare('UPDATE inventory_balances SET quantity = ? WHERE location_id = ? AND product_id = ?');
        $stmt->execute([$newQuantity, $locationId, $productId]);
    }

    $stmt = $db->prepare('INSERT INTO inventory_movements (location_id, product_id, movement_type, quantity_delta, reference_type, reference_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$locationId, $productId, $type, $delta, $referenceType, $referenceId, $notes, $userId]);
}

function adjust_inventory(int $locationId, int $productId, float $delta, int $userId, string $notes): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        move_inventory($db, $locationId, $productId, $delta, $delta >= 0 ? 'initial' : 'manual_adjustment', $userId, 'manual', null, $notes);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function batch_adjust_inventory(int $locationId, array $productIds, array $deltas, int $userId, array $notes): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        foreach ($productIds as $idx => $productId) {
            $productId = (int) $productId;
            $delta = (float) $deltas[$idx];
            $note = trim((string) ($notes[$idx] ?? 'تسوية مخزون'));
            if ($note === '') {
                $note = 'تسوية مخزون';
            }
            if ($productId > 0 && $delta !== 0.0) {
                move_inventory($db, $locationId, $productId, $delta, $delta >= 0 ? 'initial' : 'manual_adjustment', $userId, 'manual', null, $note);
            }
        }
        log_audit($userId, 'adjust', 'inventory', null, 'تسوية مخزون دفعة واحدة في موقع #' . $locationId);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function customer_rows(array $filters = [], ?int $locationId = null): array
{
    $effectiveLocation = 'COALESCE(c.location_id, derived.location_id)';
    $sql = 'SELECT c.*, ' . $effectiveLocation . ' AS effective_location_id, COALESCE(l.name, derived_location.name) AS location_name, u.name AS created_by_name
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, MIN(location_id) AS location_id
                FROM invoices
                WHERE customer_id IS NOT NULL AND location_id IS NOT NULL
                GROUP BY customer_id
            ) derived ON derived.customer_id = c.id
            LEFT JOIN locations l ON l.id = c.location_id
            LEFT JOIN locations derived_location ON derived_location.id = derived.location_id
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.is_active = 1';
    $params = [];

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $sql .= ' AND c.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $sql .= ' AND c.created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    if ($locationId !== null) {
        $sql .= ' AND ' . $effectiveLocation . ' = ?';
        $params[] = $locationId;
    } else {
        $filterLocationId = (int) ($filters['location_id'] ?? 0);
        if ($filterLocationId > 0) {
            $sql .= ' AND ' . $effectiveLocation . ' = ?';
            $params[] = $filterLocationId;
        }
    }

    $sql .= ' ORDER BY c.created_at DESC, c.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function all_customers(array $filters = [], ?int $locationId = null): array
{
    return customer_rows($filters, $locationId);
}

function all_users(): array
{
    return pdo()->query('SELECT u.*, r.name AS role_name, r.code AS role_code, l.name AS location_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN locations l ON l.id = u.location_id ORDER BY u.created_at DESC, u.id DESC')->fetchAll();
}

function all_roles(): array
{
    return pdo()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
}

function add_user(array $data): void
{
    $db = pdo();
    // Ensure column exists for backward compatibility
    $col = $db->query("SHOW COLUMNS FROM users LIKE 'working_days'")->fetch();
    if (!$col) {
        $db->exec('ALTER TABLE users ADD COLUMN working_days INT NOT NULL DEFAULT 0');
    }
    $stmt = $db->prepare('INSERT INTO users (name, username, password_hash, role_id, location_id, basic_salary, commission_percent, working_days, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['name'],
        $data['username'],
        password_hash($data['password'], PASSWORD_DEFAULT),
        (int) $data['role_id'],
        $data['location_id'] !== '' ? (int) $data['location_id'] : null,
        (float) ($data['basic_salary'] ?? 0),
        (float) ($data['commission_percent'] ?? 0),
        (int) ($data['working_days'] ?? 0),
        isset($data['is_active']) ? (int) $data['is_active'] : 1
    ]);
}

function find_user(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT u.*, r.name AS role_name, r.code AS role_code, l.name AS location_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN locations l ON l.id = u.location_id WHERE u.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_user(array $data): void
{
    $db = pdo();
    // ensure working_days column exists
    $col = $db->query("SHOW COLUMNS FROM users LIKE 'working_days'")->fetch();
    if (!$col) {
        $db->exec('ALTER TABLE users ADD COLUMN working_days INT NOT NULL DEFAULT 0');
    }
    $db->beginTransaction();
    try {
        $id = (int) $data['id'];
        $user = find_user($id);
        if (!$user) throw new RuntimeException('الموظف غير موجود.');

        $passwordHash = $user['password_hash'];
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $stmt = $db->prepare('UPDATE users SET name = ?, username = ?, password_hash = ?, role_id = ?, location_id = ?, basic_salary = ?, commission_percent = ?, working_days = ?, is_active = ? WHERE id = ?');
        $stmt->execute([
            $data['name'],
            $data['username'],
            $passwordHash,
            (int) $data['role_id'],
            $data['location_id'] !== '' ? (int) $data['location_id'] : null,
            (float) ($data['basic_salary'] ?? 0),
            (float) ($data['commission_percent'] ?? 0),
            (int) ($data['working_days'] ?? 0),
            isset($data['is_active']) ? (int) $data['is_active'] : 1,
            $id
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function deactivate_user(int $id): void
{
    $stmt = pdo()->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
    $stmt->execute([$id]);
}

function delete_user_permanently(int $id): void
{
    $user = find_user($id);
    if (!$user) {
        throw new RuntimeException('الموظف غير موجود.');
    }
    if ((int)$id === 1 || $user['username'] === 'admin') {
        throw new RuntimeException('لا يمكن حذف المدير الأساسي.');
    }

    $db = pdo();
    try {
        $db->beginTransaction();
        
        $adminId = (int) $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetchColumn() ?: 1;
        
        $tablesToReassign = [
            'locations' => ['manager_id'],
            'products' => ['created_by', 'updated_by'],
            'recipes' => ['created_by'],
            'inventory_balances' => ['last_updated_by'],
            'inventory_movements' => ['user_id'],
            'inventory_transfers' => ['created_by', 'received_by'],
            'invoices' => ['created_by', 'updated_by'],
            'invoice_items' => ['created_by', 'updated_by'],
            'expenses' => ['created_by'],
            'audit_logs' => ['user_id'],
            'branch_cash_transfers' => ['created_by', 'received_by'],
            'manager_collections' => ['created_by', 'received_by'],
            'financial_ledger' => ['created_by'],
            'payroll_adjustments' => ['created_by'],
            'payroll_overrides' => ['updated_by'],
            'wasted_products' => ['created_by'],
            'customers' => ['created_by']
        ];
        
        foreach ($tablesToReassign as $table => $columns) {
            foreach ($columns as $col) {
                try {
                    $stmt = $db->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ?");
                    $stmt->execute([$adminId, $id]);
                } catch(Throwable $ex) {}
            }
        }
        
        try { $db->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$id]); } catch(Throwable $e) {}
        try { $db->prepare('DELETE FROM attendance_records WHERE user_id = ?')->execute([$id]); } catch(Throwable $e) {}
        try { $db->prepare('DELETE FROM payroll_adjustments WHERE user_id = ?')->execute([$id]); } catch(Throwable $e) {}
        try { $db->prepare('DELETE FROM payroll_overrides WHERE user_id = ?')->execute([$id]); } catch(Throwable $e) {}

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw new RuntimeException('حدث خطأ أثناء محاولة الحذف الإجباري للموظف. استخدم التعطيل بدلاً من ذلك. (' . $e->getMessage() . ')');
    }
}

function add_customer(array $data): int
{
    $user = current_user();
    $locationId = isset($data['location_id']) && $data['location_id'] !== '' ? (int) $data['location_id'] : current_user_location_id();
    $createdBy = isset($data['created_by']) && $data['created_by'] !== '' ? (int) $data['created_by'] : ($user ? (int) $user['id'] : null);
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($phone !== '') {
        $existing = find_customer_by_phone($phone);
        if ($existing) {
            if ((int) $existing['is_active'] === 0) {
                $stmt = pdo()->prepare('UPDATE customers SET name = ?, source = ?, notes = ?, location_id = COALESCE(location_id, ?), created_by = COALESCE(created_by, ?), is_active = 1 WHERE id = ?');
                $stmt->execute([$data['name'], $data['source'], $data['notes'] ?: null, $locationId, $createdBy, (int) $existing['id']]);
                log_audit($user ? (int)$user['id'] : null, 'update', 'customer', (int) $existing['id'], 'إعادة تفعيل العميل: ' . $data['name']);
                return (int) $existing['id'];
            }
            throw new RuntimeException('رقم الهاتف مسجل بالفعل للعميل: ' . $existing['name']);
        }
    }

    // Prevent duplicate names
    $stmtName = pdo()->prepare('SELECT id, phone FROM customers WHERE name = ? AND is_active = 1 LIMIT 1');
    $stmtName->execute([trim($data['name'])]);
    $existingName = $stmtName->fetch();
    if ($existingName) {
        $msg = 'الاسم (' . $data['name'] . ') مسجل بالفعل لعميل آخر.';
        if ($existingName['phone']) {
            $msg .= ' (رقم هاتفه: ' . $existingName['phone'] . ')';
        }
        $msg .= ' يرجى إضافة لقب أو رقم لتمييزه، أو استخدام العميل المسجل.';
        throw new RuntimeException($msg);
    }

    $bdate = !empty($data['birthdate']) ? trim($data['birthdate']) : null;
    if ($bdate && preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $bdate, $m)) {
        $bdate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    $stmt = pdo()->prepare('INSERT INTO customers (name, phone, source, notes, birthdate, location_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['name'],
        $phone !== '' ? $phone : null,
        $data['source'],
        $data['notes'] ?: null,
        $bdate,
        $locationId,
        $createdBy,
    ]);
    $customerId = (int) pdo()->lastInsertId();
    log_audit($user ? (int)$user['id'] : null, 'create', 'customer', $customerId, 'إضافة العميل الجديد: ' . $data['name']);
    return $customerId;
}

function find_customer_by_phone(string $phone): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM customers WHERE phone = ? ORDER BY is_active DESC, id DESC LIMIT 1');
    $stmt->execute([$phone]);
    return $stmt->fetch() ?: null;
}

function find_customer(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_customer(array $data): void
{
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($phone !== '') {
        $stmt = pdo()->prepare('SELECT * FROM customers WHERE phone = ? AND id <> ? LIMIT 1');
        $stmt->execute([$phone, (int) $data['id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            throw new RuntimeException('رقم الهاتف مسجل بالفعل للعميل: ' . $existing['name']);
        }
    }

    $bdate = !empty($data['birthdate']) ? trim($data['birthdate']) : null;
    if ($bdate && preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $bdate, $m)) {
        $bdate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    $stmt = pdo()->prepare('UPDATE customers SET name = ?, phone = ?, source = ?, notes = ?, birthdate = ? WHERE id = ?');
    $stmt->execute([
        $data['name'], 
        $phone !== '' ? $phone : null, 
        $data['source'], 
        $data['notes'] ?: null, 
        $bdate,
        (int) $data['id']
    ]);
}

function deactivate_customer(int $id): void
{
    $stmt = pdo()->prepare('UPDATE customers SET is_active = 0 WHERE id = ?');
    $stmt->execute([$id]);
}

function delete_customer(int $id): string
{
    $db = pdo();
    $customer = find_customer($id);
    if (!$customer) {
        throw new RuntimeException('العميل غير موجود.');
    }
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $db->commit();
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'delete', 'customer', $id, 'حذف العميل نهائياً: ' . $customer['name']);
        return 'permanently_deleted';
    } catch (Throwable $e) {
        $db->rollBack();
        deactivate_customer($id);
        $user = current_user();
        log_audit($user ? (int)$user['id'] : null, 'deactivate', 'customer', $id, 'تعطيل وإخفاء العميل: ' . $customer['name']);
        return 'deactivated';
    }
}

function customer_invoices(int $customerId): array
{
    $stmt = pdo()->prepare('SELECT i.*, l.name AS location_name, u.name AS user_name, c.name AS customer_name FROM invoices i JOIN locations l ON l.id = i.location_id JOIN users u ON u.id = i.user_id LEFT JOIN customers c ON c.id = i.customer_id WHERE i.customer_id = ? ORDER BY i.created_at DESC');
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function customer_recent_invoices_for_pos(?int $customerId, ?int $locationId = null, int $limit = 20, ?string $searchQuery = null): array
{
    $db = pdo();
    $sql = "
        SELECT i.id, i.invoice_number, i.total, i.paid_total, i.due_total, i.status, i.notes,
               i.created_at, l.name AS location_name, u.name AS user_name, c.name AS customer_name, c.phone AS customer_phone
        FROM invoices i
        JOIN locations l ON l.id = i.location_id
        JOIN users u ON u.id = i.user_id
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.status = 'completed'
    ";
    $params = [];
    if ($customerId !== null && $customerId > 0) {
        $sql .= " AND i.customer_id = ?";
        $params[] = $customerId;
    }
    if ($locationId !== null && $locationId > 0) {
        $sql .= " AND i.location_id = ?";
        $params[] = $locationId;
    }
    if ($searchQuery !== null && trim($searchQuery) !== '') {
        $q = '%' . trim($searchQuery) . '%';
        $sql .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    $sql .= " ORDER BY i.created_at DESC LIMIT " . (int)$limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();

    foreach ($invoices as &$inv) {
        $invId = (int)$inv['id'];
        $stmtLines = $db->prepare("
            SELECT il.id, il.line_type, il.product_id, il.recipe_id, il.offer_id,
                   il.description, il.quantity, il.unit_price, il.discount_amount, il.line_total
            FROM invoice_lines il
            WHERE il.invoice_id = ?
            ORDER BY il.id ASC
        ");
        $stmtLines->execute([$invId]);
        $lines = $stmtLines->fetchAll();

        $inv['lines'] = $lines;
        $inv['formatted_date'] = date('Y-m-d H:i', strtotime($inv['created_at']));
    }
    unset($inv);

    return $invoices;
}

function customer_debts_rows(int $customerId): array
{
    $stmt = pdo()->prepare("
        SELECT d.*, 
               COALESCE(i.invoice_number, CONCAT('دين مباشر', IF(d.notes IS NOT NULL AND d.notes != '', CONCAT(' (', d.notes, ')'), ''))) AS invoice_number,
               COALESCE(l.name, 'دين عام') AS location_name
        FROM customer_debts d 
        LEFT JOIN invoices i ON i.id = d.invoice_id 
        LEFT JOIN locations l ON l.id = COALESCE(d.location_id, i.location_id)
        WHERE d.customer_id = ? 
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function customer_loyalty_rows(int $customerId): array
{
    return [];
}

function add_customer_payment(int $debtId, float $amount, string $method, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT d.*, i.customer_id AS inv_customer_id, i.location_id AS inv_location_id FROM customer_debts d LEFT JOIN invoices i ON i.id = d.invoice_id WHERE d.id = ? FOR UPDATE');
        $stmt->execute([$debtId]);
        $debt = $stmt->fetch();
        if (!$debt || $amount <= 0) {
            throw new RuntimeException('دفعة الدين غير صحيحة.');
        }
        $amount = min($amount, (float) $debt['remaining_amount']);
        $remaining = (float) $debt['remaining_amount'] - $amount;
        $status = $remaining <= 0.0001 ? 'paid' : 'open';
        $stmt = $db->prepare('UPDATE customer_debts SET paid_amount = paid_amount + ?, remaining_amount = ?, status = ?, closed_at = IF(? = "paid", NOW(), NULL) WHERE id = ?');
        $stmt->execute([$amount, $remaining, $status, $status, $debtId]);

        if (!empty($debt['invoice_id'])) {
            $stmt = $db->prepare('UPDATE invoices SET paid_total = paid_total + ?, due_total = GREATEST(0, due_total - ?) WHERE id = ?');
            $stmt->execute([$amount, $amount, $debt['invoice_id']]);
        }

        $targetCustomerId = (int) ($debt['customer_id'] ?: $debt['inv_customer_id']);
        $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, "debt_payment", ?, ?, ?)');
        $stmt->execute([!empty($debt['invoice_id']) ? $debt['invoice_id'] : null, $targetCustomerId, $method, $amount, $userId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function formula_defaults_rows(): array
{
    return pdo()->query("
        SELECT f.*, 
               pb.name AS bottle_name, 
               pp.name AS perfume_name,
               pd.price_per_gram AS perfume_price_per_gram,
               pd.quality_grade AS perfume_quality_grade
        FROM formula_defaults f
        LEFT JOIN products pb ON pb.id = f.bottle_product_id
        LEFT JOIN products pp ON pp.id = f.perfume_product_id
        LEFT JOIN product_perfume_details pd ON pd.product_id = pp.id
        ORDER BY f.bottle_size_ml, pp.name
    ")->fetchAll();
}

function formula_defaults_for_bottle(int $bottleProductId, ?string $qualityGrade = null): array
{
    if ($bottleProductId <= 0) {
        return [];
    }

    $sql = "
        SELECT f.*, 
               pb.name AS bottle_name, 
               pp.name AS perfume_name,
               pd.price_per_gram AS perfume_price_per_gram,
               pd.quality_grade AS perfume_quality_grade
        FROM formula_defaults f
        LEFT JOIN products pb ON pb.id = f.bottle_product_id
        LEFT JOIN products pp ON pp.id = f.perfume_product_id
        LEFT JOIN product_perfume_details pd ON pd.product_id = pp.id
        WHERE f.bottle_product_id = ?
    ";
    $params = [$bottleProductId];
    if ($qualityGrade !== null) {
        $sql .= " AND COALESCE(pd.quality_grade, '') = ? ";
        $params[] = $qualityGrade;
    }
    $sql .= " ORDER BY pp.name, f.id ";
    
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function upsert_formula_default(array $data): void
{
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    $bottleProductId = !empty($data['bottle_id']) ? (int) $data['bottle_id'] : null;
    $bottleSizeMl = (int) ($data['bottle_size_ml'] ?? 0);
    $hasMultipleRows = is_array($data['perfume_product_id'] ?? null)
        || is_array($data['default_grams'] ?? null)
        || is_array($data['price'] ?? null);

    if (!$bottleProductId || $bottleSizeMl <= 0) {
        throw new RuntimeException('الزجاجة وحجم الزجاجة مطلوبين.');
    }

    if (!$hasMultipleRows) {
        $perfumeProductId = !empty($data['perfume_product_id']) ? (int) $data['perfume_product_id'] : null;
        $defaultGrams = (float) ($data['default_grams'] ?? 0);
        $price = (float) ($data['price'] ?? 0);

        if (!$perfumeProductId) {
            throw new RuntimeException('الزيت العطري مطلوب.');
        }
        if ($defaultGrams <= 0) {
            throw new RuntimeException('الجرام الافتراضي يجب أن يكون أكبر من صفر.');
        }
        if ($price < 0) {
            throw new RuntimeException('سعر التركيبة لا يمكن أن يكون أقل من صفر.');
        }

        if ($id > 0) {
            $stmt = pdo()->prepare('UPDATE formula_defaults SET bottle_product_id = ?, perfume_product_id = ?, bottle_size_ml = ?, default_grams = ?, price = ? WHERE id = ?');
            $stmt->execute([$bottleProductId, $perfumeProductId, $bottleSizeMl, $defaultGrams, $price, $id]);
        } else {
            $stmt = pdo()->prepare('INSERT INTO formula_defaults (bottle_product_id, perfume_product_id, bottle_size_ml, default_grams, price) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE default_grams = VALUES(default_grams), price = VALUES(price)');
            $stmt->execute([$bottleProductId, $perfumeProductId, $bottleSizeMl, $defaultGrams, $price]);
        }
        return;
    }

    $perfumeIds = array_values((array) ($data['perfume_product_id'] ?? []));
    $gramsRows = array_values((array) ($data['default_grams'] ?? []));
    $priceRows = array_values((array) ($data['price'] ?? []));
    $rowCount = max(count($perfumeIds), count($gramsRows), count($priceRows));
    $rows = [];
    $seenPerfumes = [];

    for ($idx = 0; $idx < $rowCount; $idx++) {
        $perfumeProductId = (int) ($perfumeIds[$idx] ?? 0);
        $gramsRaw = isset($gramsRows[$idx]) ? trim((string) $gramsRows[$idx]) : '';
        $priceRaw = isset($priceRows[$idx]) ? trim((string) $priceRows[$idx]) : '';
        $defaultGrams = $gramsRaw !== '' ? (float) $gramsRaw : 0.0;
        $price = $priceRaw !== '' ? (float) $priceRaw : 0.0;

        if ($perfumeProductId <= 0 && $gramsRaw === '' && ($priceRaw === '' || $price == 0.0)) {
            continue;
        }
        if ($perfumeProductId <= 0) {
            throw new RuntimeException('يجب اختيار العطر لكل صف مكتمل.');
        }
        if ($defaultGrams <= 0) {
            throw new RuntimeException('الجرامات الافتراضية يجب أن تكون أكبر من صفر لكل عطر.');
        }
        if ($price < 0) {
            throw new RuntimeException('سعر بيع التركيبة لا يمكن أن يكون بالسالب.');
        }
        if (isset($seenPerfumes[$perfumeProductId])) {
            throw new RuntimeException('لا يمكن تكرار نفس العطر داخل نفس الطلب.');
        }

        $seenPerfumes[$perfumeProductId] = true;
        $rows[] = [$perfumeProductId, $defaultGrams, $price];
    }

    if (!$rows) {
        throw new RuntimeException('يجب إضافة عطر واحد صالح على الأقل للزجاجة.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO formula_defaults (bottle_product_id, perfume_product_id, bottle_size_ml, default_grams, price) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE default_grams = VALUES(default_grams), price = VALUES(price)');
        foreach ($rows as [$perfumeProductId, $defaultGrams, $price]) {
            $stmt->execute([$bottleProductId, $perfumeProductId, $bottleSizeMl, $defaultGrams, $price]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function replace_formula_defaults_for_bottle(array $data): void
{
    $bottleProductId = !empty($data['bottle_id']) ? (int) $data['bottle_id'] : null;
    $bottleSizeMl = (int) ($data['bottle_size_ml'] ?? 0);

    if (!$bottleProductId || $bottleSizeMl <= 0) {
        throw new RuntimeException('الزجاجة وحجم الزجاجة مطلوبين.');
    }

    $perfumeIds = array_values((array) ($data['perfume_product_id'] ?? []));
    $gramsRows = array_values((array) ($data['default_grams'] ?? []));
    $priceRows = array_values((array) ($data['price'] ?? []));
    $rowCount = max(count($perfumeIds), count($gramsRows), count($priceRows));
    $rows = [];
    $selectedPerfumeIds = [];
    $seenPerfumes = [];

    for ($idx = 0; $idx < $rowCount; $idx++) {
        $perfumeProductId = (int) ($perfumeIds[$idx] ?? 0);
        $gramsRaw = isset($gramsRows[$idx]) ? trim((string) $gramsRows[$idx]) : '';
        $priceRaw = isset($priceRows[$idx]) ? trim((string) $priceRows[$idx]) : '';
        $defaultGrams = $gramsRaw !== '' ? (float) $gramsRaw : 0.0;
        $price = $priceRaw !== '' ? (float) $priceRaw : 0.0;

        if ($perfumeProductId <= 0 && $gramsRaw === '' && ($priceRaw === '' || $price == 0.0)) {
            continue;
        }
        if ($perfumeProductId <= 0) {
            throw new RuntimeException('يجب اختيار العطر لكل صف مكتمل.');
        }
        if ($defaultGrams <= 0) {
            throw new RuntimeException('الجرامات الافتراضية يجب أن تكون أكبر من صفر لكل عطر.');
        }
        if ($price < 0) {
            throw new RuntimeException('سعر بيع التركيبة لا يمكن أن يكون بالسالب.');
        }
        if (isset($seenPerfumes[$perfumeProductId])) {
            throw new RuntimeException('لا يمكن تكرار نفس العطر داخل نفس الطلب.');
        }

        $seenPerfumes[$perfumeProductId] = true;
        $selectedPerfumeIds[] = $perfumeProductId;
        $rows[] = [$perfumeProductId, $defaultGrams, $price];
    }

    if (!$rows) {
        throw new RuntimeException('يجب إضافة عطر واحد صالح على الأقل للزجاجة.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        $qualityGrade = isset($data['quality_grade']) && $data['quality_grade'] !== '' ? $data['quality_grade'] : null;
        if ($qualityGrade !== null) {
            $placeholders = implode(',', array_fill(0, count($selectedPerfumeIds), '?'));
            $deleteStmt = $db->prepare("
                DELETE FROM formula_defaults 
                WHERE bottle_product_id = ? 
                  AND perfume_product_id IN (
                      SELECT product_id FROM product_perfume_details WHERE COALESCE(quality_grade, '') = ?
                  )
                  AND perfume_product_id NOT IN ($placeholders)
            ");
            $deleteStmt->execute(array_merge([$bottleProductId, $qualityGrade], $selectedPerfumeIds));
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedPerfumeIds), '?'));
            $deleteStmt = $db->prepare("DELETE FROM formula_defaults WHERE bottle_product_id = ? AND perfume_product_id NOT IN ($placeholders)");
            $deleteStmt->execute(array_merge([$bottleProductId], $selectedPerfumeIds));
        }

        $upsertStmt = $db->prepare('INSERT INTO formula_defaults (bottle_product_id, perfume_product_id, bottle_size_ml, default_grams, price) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE default_grams = VALUES(default_grams), price = VALUES(price)');
        foreach ($rows as [$perfumeProductId, $defaultGrams, $price]) {
            $upsertStmt->execute([$bottleProductId, $perfumeProductId, $bottleSizeMl, $defaultGrams, $price]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function saved_recipes(): array
{
    return pdo()->query('SELECT r.*, p.name AS bottle_name, b.size_ml FROM recipe_headers r JOIN products p ON p.id = r.bottle_product_id LEFT JOIN product_bottle_details b ON b.product_id = r.bottle_product_id WHERE r.is_active = 1 ORDER BY r.id DESC')->fetchAll();
}

function recipe_components_for(int $recipeId): array
{
    $stmt = pdo()->prepare('SELECT rc.*, p.name AS perfume_name FROM recipe_components rc JOIN products p ON p.id = rc.perfume_product_id WHERE rc.recipe_id = ? ORDER BY rc.id');
    $stmt->execute([$recipeId]);
    return $stmt->fetchAll();
}

function add_recipe(array $data): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO products (name, type, unit, sale_price, barcode) VALUES (?, "recipe", "unit", ?, ?)');
        $stmt->execute([$data['name'], (float) $data['default_sale_price'], generate_ean13()]);
        $productId = (int) $db->lastInsertId();
        $stmt = $db->prepare('INSERT INTO recipe_headers (product_id, name, bottle_product_id, default_sale_price) VALUES (?, ?, ?, ?)');
        $stmt->execute([$productId, $data['name'], (int) $data['bottle_product_id'], (float) $data['default_sale_price']]);
        $recipeId = (int) $db->lastInsertId();
        foreach (($data['perfume_product_id'] ?? []) as $idx => $perfumeId) {
            $grams = (float) ($data['grams'][$idx] ?? 0);
            if ((int) $perfumeId > 0 && $grams > 0) {
                $stmt = $db->prepare('INSERT INTO recipe_components (recipe_id, perfume_product_id, grams) VALUES (?, ?, ?)');
                $stmt->execute([$recipeId, (int) $perfumeId, $grams]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function next_invoice_number(PDO $db, int $locationId): string
{
    // جلب أكبر رقم معرّف ID في الفواتير (مثلاً 2051)
    $lastId = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM invoices")->fetchColumn();
    
    // جلب أكبر رقم تسلسلي مسجل في الفواتير السابقة
    $lastSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)), 0) FROM invoices")->fetchColumn();
    
    $seq = max($lastId, $lastSeq) + 1;

    // ترقيم متسلسل صريح ومستمر: 2052، 2053... كما يريده العميل
    return (string) $seq;
}

function line_discount(float $gross, ?string $type, float $value): float
{
    if ($type === 'percent') {
        return min($gross, $gross * ($value / 100));
    }
    if ($type === 'amount') {
        return min($gross, $value);
    }
    return 0;
}

function create_invoice(array $data, array $user): int
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $locationId = (int) $data['location_id'];
        require_location_access($locationId);
        require_location_type($locationId, ['branch', 'warehouse'], 'البيع مسموح من الفروع والمخزن الرئيسي فقط. الأونلاين لا يسجل فواتير بيع.');
        $customerId = $data['customer_id'] !== '' ? (int) $data['customer_id'] : null;
        $lines = [];
        $subtotal = 0.0;

        foreach (($data['product_id'] ?? []) as $idx => $productIdRaw) {
            $productId = (int) $productIdRaw;
            $quantity = (float) ($data['quantity'][$idx] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $product = find_product($productId);
            if (!$product) {
                throw new RuntimeException('صنف غير موجود.');
            }
            $unitPrice = (float) ($data['unit_price'][$idx] ?? $product['sale_price']);
            $gross = $quantity * $unitPrice;
            $discountType = ($data['line_discount_type'][$idx] ?? '') ?: null;
            $discountValue = (float) ($data['line_discount_value'][$idx] ?? 0);
            $discount = line_discount($gross, $discountType, $discountValue);
            $total = $gross - $discount;
            $lines[] = [
                'kind' => 'product',
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discount,
                'line_total' => $total,
            ];
            $subtotal += $total;
        }

        // Handle instant mixes from cart (mix_data[])
        foreach (($data['mix_data'] ?? []) as $mixRaw) {
            $mix = json_decode($mixRaw, true);
            if (!is_array($mix) || empty($mix['components'])) {
                continue;
            }

            $isWithoutBottle = !empty($mix['without_bottle']);
            $bottleId = (int) ($mix['bottle_id'] ?? 0);
            $mixSalePrice = (float) ($mix['sale_price'] ?? 0);

            if ($mixSalePrice < 0) {
                continue;
            }
            if (!$isWithoutBottle && $mixSalePrice <= 0) {
                continue;
            }

            if (!$isWithoutBottle) {
                if ($bottleId <= 0) {
                    continue;
                }
                $bottle = find_product($bottleId);
                if (!$bottle || $bottle['type'] !== 'bottle') {
                    throw new RuntimeException('اختر زجاجة صحيحة للتركيبة: ' . ($bottle['name'] ?? ''));
                }
                $components = [['product' => $bottle, 'quantity' => 1.0]];
                $descriptionParts = [$bottle['name']];
            } else {
                $components = [];
                $descriptionParts = ['بدون زجاجة'];
            }

            foreach ($mix['components'] as $comp) {
                $perfumeId = (int) ($comp['product_id'] ?? 0);
                $grams = (float) ($comp['grams'] ?? 0);
                $defaultGrams = (float) ($comp['default_grams'] ?? 0);
                if ($perfumeId <= 0 || $grams <= 0) {
                    continue;
                }
                $perfume = find_product($perfumeId);
                if (!$perfume || $perfume['type'] !== 'perfume_gram') {
                    throw new RuntimeException('اختر عطر صحيح داخل التركيبة.');
                }
                $components[] = ['product' => $perfume, 'quantity' => $grams];
                $gramsLabel = $defaultGrams > 0 && abs($grams - $defaultGrams) > 0.01 ? qty($grams) . 'جم (الأساسي ' . qty($defaultGrams) . 'جم)' : qty($grams) . 'جم';
                $descriptionParts[] = $perfume['name'] . ' ' . $gramsLabel;
            }

            if (($isWithoutBottle && count($components) >= 1) || (!$isWithoutBottle && count($components) > 1)) {
                // تحقق من تعديل السعر
                $defaultPrice = (float) ($mix['default_price'] ?? $mixSalePrice);
                $priceWarning = ($defaultPrice > 0 && abs($mixSalePrice - $defaultPrice) > 0.5)
                    ? ' [سعر: ' . $mixSalePrice . ' ج.م ، الأساسي: ' . $defaultPrice . ' ج.م]'
                    : '';
                $lines[] = [
                    'kind'           => 'custom_recipe',
                    'description'    => 'تركيبة فورية: ' . implode(' + ', $descriptionParts) . $priceWarning,
                    'quantity'       => 1.0,
                    'unit_price'     => $mixSalePrice,
                    'discount_type'  => null,
                    'discount_value' => 0.0,
                    'discount_amount'=> 0.0,
                    'line_total'     => $mixSalePrice,
                    'components'     => $components,
                ];
                $subtotal += $mixSalePrice;
            }
        }

        foreach (($data['recipe_id'] ?? []) as $index => $recipeIdRaw) {
            $recipeId = (int) $recipeIdRaw;
            if ($recipeId <= 0) {
                continue;
            }
            $stmt = $db->prepare('SELECT r.*, p.sale_price FROM recipe_headers r LEFT JOIN products p ON p.id = r.product_id WHERE r.id = ? AND r.is_active = 1');
            $stmt->execute([$recipeId]);
            $recipe = $stmt->fetch();
            if (!$recipe) {
                throw new RuntimeException('تركيبة جاهزة غير موجودة.');
            }

            $chosenBottleId = $data['recipe_bottle_id'][$index] ?? 'default';
            $isWithoutBottle = (!empty($data['recipe_without_bottle'][$index]) && $data['recipe_without_bottle'][$index] === '1')
                               || ($chosenBottleId === 'no_bottle');

            $components = [];
            $bottleDesc = '';

            if (!$isWithoutBottle) {
                $bottleIdToUse = ($chosenBottleId !== 'default' && (int)$chosenBottleId > 0)
                    ? (int)$chosenBottleId
                    : (int)$recipe['bottle_product_id'];
                if ($bottleIdToUse > 0) {
                    $bottle = find_product($bottleIdToUse);
                    if ($bottle) {
                        $components[] = ['product' => $bottle, 'quantity' => 1.0];
                        $bottleDesc = ' (' . $bottle['name'] . ')';
                    }
                }
            } else {
                $bottleDesc = ' (بدون زجاجة)';
            }

            foreach (recipe_components_for($recipeId) as $component) {
                $perfume = find_product((int) $component['perfume_product_id']);
                $components[] = ['product' => $perfume, 'quantity' => (float) $component['grams']];
            }
            $price = (float) ($recipe['default_sale_price'] ?: $recipe['sale_price']);
            $lines[] = [
                'kind' => 'saved_recipe',
                'recipe_id' => $recipeId,
                'description' => 'تركيبة جاهزة: ' . $recipe['name'] . $bottleDesc,
                'quantity' => 1.0,
                'unit_price' => $price,
                'line_total' => $price,
                'components' => $components,
            ];
            $subtotal += $price;
        }

        // Handle Offers from cart
        foreach (($data['offer_id'] ?? []) as $index => $offerIdRaw) {
            $offerId = (int) $offerIdRaw;
            if ($offerId <= 0) {
                continue;
            }
            $offer = find_offer_with_items($offerId);
            if (!$offer) {
                throw new RuntimeException('العرض المحدد غير موجود.');
            }
            $offerQty = max(0.01, (float)($data['offer_qty'][$index] ?? 1.0));
            $offerPrice = isset($data['offer_price'][$index]) && $data['offer_price'][$index] !== '' ? (float)$data['offer_price'][$index] : (float)$offer['price_after'];
            $offerTotal = $offerQty * $offerPrice;

            $customOfferData = null;
            if (!empty($data['offer_custom_data'][$index])) {
                $customOfferData = is_array($data['offer_custom_data'][$index])
                    ? $data['offer_custom_data'][$index]
                    : json_decode((string)$data['offer_custom_data'][$index], true);
            }

            $components = [];
            $offerDescParts = [];

            if ($customOfferData && !empty($customOfferData['items'])) {
                // Customized offer items (substituted bottles and customized blends)
                foreach ($customOfferData['items'] as $cItem) {
                    $itemQty = max(0.01, (float)($cItem['quantity'] ?? 1.0));
                    if (($cItem['item_type'] ?? '') === 'product' && !empty($cItem['product_id'])) {
                        $prod = find_product((int)$cItem['product_id']);
                        if ($prod) {
                            $components[] = [
                                'product' => $prod,
                                'quantity' => $itemQty * $offerQty,
                            ];
                            $offerDescParts[] = $prod['name'] . ($itemQty > 1 ? " ({$itemQty})" : '');
                        }
                    } elseif (($cItem['item_type'] ?? '') === 'recipe') {
                        $bottleId = (!empty($cItem['bottle_id']) && $cItem['bottle_id'] !== 'no_bottle') ? (int)$cItem['bottle_id'] : null;
                        $bottleName = '';
                        if ($bottleId) {
                            $bottle = find_product($bottleId);
                            if ($bottle) {
                                $components[] = [
                                    'product' => $bottle,
                                    'quantity' => $itemQty * $offerQty,
                                ];
                                $bottleName = $bottle['name'];
                            }
                        }
                        $oilParts = [];
                        foreach (($cItem['oils'] ?? []) as $oil) {
                            $perfumeId = (int)($oil['perfume_id'] ?? 0);
                            $grams = (float)($oil['grams'] ?? 0);
                            if ($perfumeId > 0 && $grams > 0) {
                                $perfume = find_product($perfumeId);
                                if ($perfume) {
                                    $components[] = [
                                        'product' => $perfume,
                                        'quantity' => $grams * $itemQty * $offerQty,
                                    ];
                                    $oilParts[] = $perfume['name'] . ' ' . $grams . 'جم';
                                }
                            }
                        }
                        $recTitle = !empty($cItem['recipe_name']) ? $cItem['recipe_name'] : 'تركيبة';
                        $subDesc = $recTitle;
                        if ($bottleName) $subDesc .= " ({$bottleName})";
                        if (!empty($oilParts)) $subDesc .= " [" . implode(' + ', $oilParts) . "]";
                        $offerDescParts[] = ($itemQty > 1 ? "{$itemQty}× " : '') . $subDesc;
                    }
                }
            } else {
                // Default offer items from db
                foreach ($offer['items'] as $item) {
                    if ($item['item_type'] === 'product' && !empty($item['product_id'])) {
                        $prod = find_product((int)$item['product_id']);
                        if ($prod) {
                            $components[] = [
                                'product' => $prod,
                                'quantity' => (float)$item['quantity'] * $offerQty,
                            ];
                        }
                    } elseif ($item['item_type'] === 'recipe') {
                        if (!empty($item['bottle_product_id'])) {
                            $bottle = find_product((int)$item['bottle_product_id']);
                            if ($bottle) {
                                $components[] = [
                                    'product' => $bottle,
                                    'quantity' => (float)$item['quantity'] * $offerQty,
                                ];
                            }
                        }
                        foreach (($item['components'] ?? []) as $comp) {
                            $perfume = find_product((int)$comp['perfume_product_id']);
                            if ($perfume) {
                                $components[] = [
                                    'product' => $perfume,
                                    'quantity' => (float)$comp['grams'] * (float)$item['quantity'] * $offerQty,
                                ];
                            }
                        }
                    }
                }
            }

            $offerDescription = 'عرض: ' . $offer['name'];
            if (!empty($offerDescParts)) {
                $offerDescription .= ' - ' . implode(' | ', $offerDescParts);
            }

            $lines[] = [
                'kind' => 'offer',
                'offer_id' => $offerId,
                'description' => $offerDescription,
                'quantity' => $offerQty,
                'unit_price' => $offerPrice,
                'line_total' => $offerTotal,
                'components' => $components,
            ];
            $subtotal += $offerTotal;
        }

        if (!$lines) {
            throw new RuntimeException('الفاتورة لا تحتوي على أصناف.');
        }

        $discountType = ($data['discount_type'] ?? '') ?: null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = line_discount($subtotal, $discountType, $discountValue);
        $total = $subtotal - $discountAmount;
        $paid = min($total, (float) ($data['paid_total'] ?? 0));
        $due = $total - $paid;
        $notes = $data['notes'] ?: '';
        if (!empty($data['packer_name'])) {
            $notes .= ($notes ? "\n" : "") . "تم التقفيل بواسطة: " . trim($data['packer_name']);
        }

        // Handle exchange / trade-in data
        $exchangeCredit = 0.0;
        $exchangeData = null;
        if (!empty($data['exchange_data'])) {
            $exchangeData = is_array($data['exchange_data']) ? $data['exchange_data'] : json_decode((string)$data['exchange_data'], true);
        }

        if ($exchangeData && !empty($exchangeData['original_invoice_id']) && !empty($exchangeData['lines'])) {
            $origInvId = (int)$exchangeData['original_invoice_id'];
            $lineIds = [];
            $returnedQtys = [];
            foreach ($exchangeData['lines'] as $exLine) {
                $lId = (int)($exLine['line_id'] ?? 0);
                $lQty = (float)($exLine['quantity'] ?? 0);
                if ($lId > 0 && $lQty > 0) {
                    $lineIds[] = $lId;
                    $returnedQtys[$lId] = $lQty;
                }
            }

            if (!empty($lineIds)) {
                $stmtOrig = $db->prepare('SELECT invoice_number, location_id FROM invoices WHERE id = ?');
                $stmtOrig->execute([$origInvId]);
                $origInv = $stmtOrig->fetch();

                $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
                $stmtLines = $db->prepare("SELECT id, quantity, line_total FROM invoice_lines WHERE invoice_id = ? AND id IN ($placeholders)");
                $stmtLines->execute(array_merge([$origInvId], $lineIds));
                $origLines = $stmtLines->fetchAll();

                $lineProportions = [];
                foreach ($origLines as $ol) {
                    $lId = (int)$ol['id'];
                    $oQty = (float)$ol['quantity'];
                    $rQty = $returnedQtys[$lId] ?? $oQty;
                    $proportion = $oQty > 0 ? ($rQty / $oQty) : 1.0;
                    $lineProportions[$lId] = $proportion;
                    $exchangeCredit += (float)$ol['line_total'] * $proportion;
                }

                $retNumber = 'RT-EX-' . date('Ymd-His') . '-' . $origInvId;
                $retReason = 'استبدال في فاتورة بيع جديدة ' . ($origInv ? '(فاتورة أصلية: ' . $origInv['invoice_number'] . ')' : '');
                $stmtRet = $db->prepare('INSERT INTO return_invoices (original_invoice_id, return_number, refund_method, amount, refund_paid, reason, created_by) VALUES (?, ?, "exchange", ?, 0, ?, ?)');
                $stmtRet->execute([$origInvId, $retNumber, $exchangeCredit, $retReason, (int)$user['id']]);
                $returnId = (int)$db->lastInsertId();

                // Restore inventory for exchanged components
                $stmtComp = $db->prepare("SELECT * FROM invoice_line_components WHERE invoice_line_id IN ($placeholders)");
                $stmtComp->execute($lineIds);
                foreach ($stmtComp->fetchAll() as $component) {
                    $lId = (int)$component['invoice_line_id'];
                    $prop = $lineProportions[$lId] ?? 1.0;
                    $restoreQty = (float)$component['quantity'] * $prop;
                    move_inventory($db, (int)($origInv['location_id'] ?? $locationId), (int)$component['component_product_id'], $restoreQty, 'return_future', (int)$user['id'], 'return', $returnId, 'استرجاع مخزون صنف مستبدل');
                }

                $exchangeNote = sprintf('عملية استبدال من فاتورة #%s (رصيد مستبدل: %.2f ج.م)', $origInv['invoice_number'] ?? (string)$origInvId, $exchangeCredit);
                $notes = $notes ? ($notes . "\n" . $exchangeNote) : $exchangeNote;
            }
        }

        $creditPaid = min($total, $exchangeCredit);
        $remainingPayable = max(0.0, $total - $creditPaid);
        $userPaidInput = (float) ($data['paid_total'] ?? 0);
        $paidFromUser = min($remainingPayable, $userPaidInput);
        $paid = $creditPaid + $paidFromUser;
        $due = max(0.0, $total - $paid);

        if ($due > 0 && !$customerId) {
            throw new RuntimeException('يجب اختيار عميل عند وجود دفع جزئي أو دين.');
        }

        $notes = $notes ?: null;

        $stmt = $db->prepare('INSERT INTO invoices (invoice_number, location_id, user_id, customer_id, subtotal, discount_type, discount_value, discount_amount, total, paid_total, due_total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            next_invoice_number($db, $locationId),
            $locationId,
            (int) $user['id'],
            $customerId,
            $subtotal,
            $discountType,
            $discountValue,
            $discountAmount,
            $total,
            $paid,
            $due,
            $notes,
        ]);
        $invoiceId = (int) $db->lastInsertId();

        foreach ($lines as $line) {
            if ($line['kind'] === 'product') {
                $stmt = $db->prepare('INSERT INTO invoice_lines (invoice_id, line_type, product_id, description, quantity, unit_price, discount_type, discount_value, discount_amount, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$invoiceId, 'product', $line['product']['id'], $line['product']['name'], $line['quantity'], $line['unit_price'], $line['discount_type'], $line['discount_value'], $line['discount_amount'], $line['line_total']]);
                $lineId = (int) $db->lastInsertId();
                move_inventory($db, $locationId, (int) $line['product']['id'], -1 * (float) $line['quantity'], 'sale', (int) $user['id'], 'invoice', $invoiceId, 'بيع فاتورة');
                $stmt = $db->prepare('INSERT INTO invoice_line_components (invoice_line_id, component_product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)');
                $stmt->execute([$lineId, $line['product']['id'], $line['quantity'], $line['product']['cost_price']]);
                continue;
            }

            if ($line['kind'] === 'offer') {
                $stmt = $db->prepare('INSERT INTO invoice_lines (invoice_id, line_type, offer_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$invoiceId, 'offer', $line['offer_id'], $line['description'], $line['quantity'], $line['unit_price'], $line['line_total']]);
                $lineId = (int) $db->lastInsertId();
                foreach ($line['components'] as $component) {
                    move_inventory($db, $locationId, (int) $component['product']['id'], -1 * (float) $component['quantity'], 'sale', (int) $user['id'], 'invoice', $invoiceId, 'خصم صنف من عرض: ' . $line['description']);
                    $stmt = $db->prepare('INSERT INTO invoice_line_components (invoice_line_id, component_product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$lineId, $component['product']['id'], $component['quantity'], $component['product']['cost_price']]);
                }
                continue;
            }

            $lineType = $line['kind'] === 'saved_recipe' ? 'saved_recipe' : 'custom_recipe';
            $stmt = $db->prepare('INSERT INTO invoice_lines (invoice_id, line_type, recipe_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$invoiceId, $lineType, $line['recipe_id'] ?? null, $line['description'], 1, $line['unit_price'], $line['line_total']]);
            $lineId = (int) $db->lastInsertId();
            foreach ($line['components'] as $component) {
                move_inventory($db, $locationId, (int) $component['product']['id'], -1 * (float) $component['quantity'], 'sale', (int) $user['id'], 'invoice', $invoiceId, 'مكون تركيبة فورية');
                $stmt = $db->prepare('INSERT INTO invoice_line_components (invoice_line_id, component_product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)');
                $stmt->execute([$lineId, $component['product']['id'], $component['quantity'], $component['product']['cost_price']]);
            }
        }

        // Record exchange credit payment if applicable
        if ($creditPaid > 0) {
            $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$invoiceId, $customerId, 'invoice_payment', 'customer_credit', $creditPaid, (int) $user['id']]);
        }

        if ($paidFromUser > 0) {
            $paymentMethod = $data['payment_method'] ?? 'cash';
            if ($paymentMethod === 'salary_deduction') {
                // خصم من راتب الموظف
                $deductionUserId = (int) ($data['deduction_user_id'] ?? 0);
                if ($deductionUserId <= 0) {
                    throw new RuntimeException('يجب اختيار الموظف عند الخصم من الراتب.');
                }
                // التحقق من وجود الموظف
                $stmtEmp = $db->prepare('SELECT id, name FROM users WHERE id = ? AND is_active = 1');
                $stmtEmp->execute([$deductionUserId]);
                $emp = $stmtEmp->fetch();
                if (!$emp) {
                    throw new RuntimeException('الموظف المحدد غير موجود أو غير نشط.');
                }
                // تسجيل الدفعة كـ salary_deduction في جدول payments للربط بالفاتورة
                $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$invoiceId, $customerId, 'invoice_payment', 'salary_deduction', $paidFromUser, (int) $user['id']]);
                // إدراج خصم في جدول payroll_adjustments لشهر الفاتورة
                $adjustmentMonth = date('Y-m');
                $reason = 'خصم مشتريات فاتورة #' . $invoiceId . ' — بتاريخ ' . date('d/m/Y');
                $stmtAdj = $db->prepare('INSERT INTO payroll_adjustments (user_id, adjustment_month, type, amount, reason, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtAdj->execute([$deductionUserId, $adjustmentMonth, 'deduction', $paidFromUser, $reason, (int) $user['id']]);
                log_audit((int)$user['id'], 'create', 'payroll_adjustment', (int)$db->lastInsertId(), 'خصم مشتريات من راتب الموظف #' . $deductionUserId . ' بقيمة ' . $paidFromUser . ' ج.م (فاتورة #' . $invoiceId . ')');
            } elseif ($paymentMethod === 'mixed_cash_instapay' || $paymentMethod === 'mixed_cash_vodafone') {
                $paidCash = (float) ($data['paid_cash'] ?? 0);
                
                if ($paymentMethod === 'mixed_cash_instapay') {
                    $secondaryMethod = 'instapay';
                    $paidSecondary = (float) ($data['paid_instapay'] ?? 0);
                } else {
                    $secondaryMethod = 'vodafone_cash';
                    $paidSecondary = (float) ($data['paid_vodafone_cash'] ?? 0);
                }
                
                $actualSecondary = min($paidSecondary, $remainingPayable);
                $actualCash = max(0.0, $paidFromUser - $actualSecondary);
                
                if ($actualCash > 0) {
                    $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$invoiceId, $customerId, 'invoice_payment', 'cash', $actualCash, (int) $user['id']]);
                }
                if ($actualSecondary > 0) {
                    $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$invoiceId, $customerId, 'invoice_payment', $secondaryMethod, $actualSecondary, (int) $user['id']]);
                }
            } else {
                $stmt = $db->prepare('INSERT INTO payments (invoice_id, customer_id, payment_type, method, amount, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$invoiceId, $customerId, 'invoice_payment', $paymentMethod, $paidFromUser, (int) $user['id']]);
            }
        }

        if ($due > 0) {
            $stmt = $db->prepare('INSERT INTO customer_debts (customer_id, invoice_id, original_amount, paid_amount, remaining_amount) VALUES (?, ?, ?, 0, ?)');
            $stmt->execute([$customerId, $invoiceId, $due, $due]);
        }

        // Loyalty points system removed.

        log_audit((int)$user['id'], 'create', 'invoice', $invoiceId, 'إنشاء فاتورة مبيعات جديدة رقم #' . $invoiceId);
        $db->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * هل الفاتورة تحتوي على تحذيرات تعديل جرامات أو سعر في التركيبات الفورية؟
 */
function invoice_has_grams_warnings(int $invoiceId): bool
{
    if ($invoiceId <= 0) return false;
    $stmt = pdo()->prepare(
        "SELECT description FROM invoice_lines WHERE invoice_id = ? AND line_type = 'custom_recipe'"
    );
    $stmt->execute([$invoiceId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $desc) {
        // الجرامات: "(الأساسي Xجم)" | السعر: "[سعر: X ج.م ، الأساسي: Y ج.م]"
        if (strpos($desc, 'الأساسي') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * حذف فاتورة مع إرجاع المخزون والمدفوعات كما كانت
 */
function delete_invoice_with_restore(int $invoiceId, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        // جلب بيانات الفاتورة
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new RuntimeException('الفاتورة غير موجودة.');
        }

        $locationId = (int) $invoice['location_id'];

        // جلب بنود الفاتورة
        $stmt = $db->prepare('SELECT il.*, ilc.component_product_id, ilc.quantity as comp_qty
            FROM invoice_lines il
            LEFT JOIN invoice_line_components ilc ON ilc.invoice_line_id = il.id
            WHERE il.invoice_id = ?');
        $stmt->execute([$invoiceId]);
        $lineRows = $stmt->fetchAll();

        // إرجاع المخزون لكل مكون
        $restoredComponents = [];
        foreach ($lineRows as $row) {
            if (!$row['component_product_id']) continue;
            $key = $locationId . '_' . $row['component_product_id'];
            if (!isset($restoredComponents[$key])) {
                $restoredComponents[$key] = [
                    'location_id' => $locationId,
                    'product_id'  => (int) $row['component_product_id'],
                    'qty'         => 0.0,
                ];
            }
            $restoredComponents[$key]['qty'] += (float) $row['comp_qty'];
        }

        foreach ($restoredComponents as $item) {
            move_inventory(
                $db,
                $item['location_id'],
                $item['product_id'],
                $item['qty'], // موجب = إرجاع للمخزون
                'void',
                $userId,
                'invoice',
                $invoiceId,
                'إلغاء فاتورة وإرجاع مخزون'
            );
        }

        // حذف ديون العميل المرتبطة بالفاتورة
        $db->prepare('DELETE FROM customer_debts WHERE invoice_id = ?')->execute([$invoiceId]);

        // حذف المدفوعات
        $db->prepare('DELETE FROM payments WHERE invoice_id = ?')->execute([$invoiceId]);

        // حذف مكونات بنود الفاتورة
        $stmt2 = $db->prepare('SELECT id FROM invoice_lines WHERE invoice_id = ?');
        $stmt2->execute([$invoiceId]);
        $lineIds = array_column($stmt2->fetchAll(), 'id');
        if ($lineIds) {
            $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
            $db->prepare("DELETE FROM invoice_line_components WHERE invoice_line_id IN ($placeholders)")->execute($lineIds);
        }

        // حذف بنود الفاتورة
        $db->prepare('DELETE FROM invoice_lines WHERE invoice_id = ?')->execute([$invoiceId]);

        // حذف الفاتورة نفسها
        $db->prepare('DELETE FROM invoices WHERE id = ?')->execute([$invoiceId]);

        log_audit($userId, 'delete', 'invoice', $invoiceId, 'حذف فاتورة رقم #' . $invoice['invoice_number'] . ' وإرجاع المخزون');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function create_transfer(array $data, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $from = (int) $data['from_location_id'];
        $to = (int) $data['to_location_id'];
        $lineItems = transfer_line_items($data);
        require_location_access($from);
        require_location_type($from, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن التحويل منه.');
        require_location_type($to, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن التحويل إليه.');
        if ($from === $to) {
            throw new RuntimeException('لا يمكن التحويل لنفس الموقع.');
        }
        assert_transfer_stock_available($db, $from, $lineItems);
        $number = 'TR-' . date('Ymd-His') . '-' . random_int(100, 999);
        $transferDate = $data['transfer_date'] ? date('Y-m-d', strtotime($data['transfer_date'])) : date('Y-m-d');
        $senderName = trim((string) ($data['sender_name'] ?? '')) ?: null;
        $receiverName = trim((string) ($data['receiver_name'] ?? '')) ?: null;
        $stmt = $db->prepare('INSERT INTO inventory_transfers (transfer_number, from_location_id, to_location_id, notes, sender_name, receiver_name, transfer_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$number, $from, $to, $data['notes'] ?: null, $senderName, $receiverName, $transferDate, $userId]);
        $transferId = (int) $db->lastInsertId();
        foreach ($lineItems as $item) {
            move_inventory($db, $from, $item['product_id'], -1 * $item['quantity'], 'transfer_future', $userId, 'transfer', $transferId, 'خروج تحويل مخزون');
            $stmt = $db->prepare('INSERT INTO inventory_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)');
            $stmt->execute([$transferId, $item['product_id'], $item['quantity']]);
        }
        log_audit($userId, 'create', 'transfer', $transferId, 'إنشاء أمر تحويل مخزني رقم ' . $number);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_transfer(array $data, int $userId, ?array $fromTypes = null, ?array $toTypes = null): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $transferId = (int) $data['transfer_id'];
        $stmt = $db->prepare('SELECT * FROM inventory_transfers WHERE id = ? FOR UPDATE');
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch();
        if (!$transfer || $transfer['status'] !== 'pending') {
            throw new RuntimeException('لا يمكن تعديل هذا التحويل أو أنه غير متاح.');
        }
        if ($fromTypes !== null && $toTypes !== null) {
            require_transfer_location_types($db, $transfer, $fromTypes, $toTypes);
        }

        $from = (int) $data['from_location_id'];
        $to = (int) $data['to_location_id'];
        $lineItems = transfer_line_items($data);
        require_location_access($from);
        require_location_type($from, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن التحويل منه.');
        require_location_type($to, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن التحويل إليه.');
        if ($from === $to) {
            throw new RuntimeException('لا يمكن التحويل لنفس الموقع.');
        }
        if (!$lineItems) {
            throw new RuntimeException('يجب إضافة صنف واحد على الأقل في التحويل.');
        }

        $stmt = $db->prepare('SELECT * FROM inventory_transfer_items WHERE transfer_id = ?');
        $stmt->execute([$transferId]);
        $oldItems = $stmt->fetchAll();
        foreach ($oldItems as $item) {
            move_inventory($db, (int)$transfer['from_location_id'], (int)$item['product_id'], (float)$item['quantity'], 'transfer_adjust', $userId, 'transfer', $transferId, 'تعديل تحويل مخزني - استرجاع كمية للمصدر');
        }

        $stmt = $db->prepare('DELETE FROM inventory_transfer_items WHERE transfer_id = ?');
        $stmt->execute([$transferId]);

        assert_transfer_stock_available($db, $from, $lineItems);

        foreach ($lineItems as $item) {
            move_inventory($db, $from, $item['product_id'], -1 * $item['quantity'], 'transfer_future', $userId, 'transfer', $transferId, 'خروج تحويل مخزون معدّل');
            $stmt = $db->prepare('INSERT INTO inventory_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)');
            $stmt->execute([$transferId, $item['product_id'], $item['quantity']]);
        }

        $transferDate = $data['transfer_date'] ? date('Y-m-d', strtotime($data['transfer_date'])) : date('Y-m-d');
        $senderName = trim((string) ($data['sender_name'] ?? '')) ?: null;
        $receiverName = trim((string) ($data['receiver_name'] ?? '')) ?: null;
        $stmt = $db->prepare('UPDATE inventory_transfers SET from_location_id = ?, to_location_id = ?, notes = ?, sender_name = ?, receiver_name = ?, transfer_date = ? WHERE id = ?');
        $stmt->execute([$from, $to, $data['notes'] ?: null, $senderName, $receiverName, $transferDate, $transferId]);

        log_audit($userId, 'update', 'transfer', $transferId, 'تعديل أمر تحويل مخزني رقم ' . $transfer['transfer_number']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function cancel_transfer(int $transferId, int $userId, ?array $fromTypes = null, ?array $toTypes = null): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM inventory_transfers WHERE id = ? FOR UPDATE');
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch();
        if (!$transfer || $transfer['status'] !== 'pending') {
            throw new RuntimeException('لا يمكن إلغاء هذا التحويل أو أنه غير متاح.');
        }
        if ($fromTypes !== null && $toTypes !== null) {
            require_transfer_location_types($db, $transfer, $fromTypes, $toTypes);
        }

        $stmt = $db->prepare('SELECT * FROM inventory_transfer_items WHERE transfer_id = ?');
        $stmt->execute([$transferId]);
        foreach ($stmt->fetchAll() as $item) {
            move_inventory($db, (int)$transfer['from_location_id'], (int)$item['product_id'], (float)$item['quantity'], 'transfer_adjust', $userId, 'transfer', $transferId, 'إلغاء تحويل مخزني - استرجاع كمية للمصدر');
        }

        $stmt = $db->prepare('UPDATE inventory_transfers SET status = "cancelled" WHERE id = ?');
        $stmt->execute([$transferId]);

        $stmt = $db->prepare('SELECT transfer_number FROM inventory_transfers WHERE id = ?');
        $stmt->execute([$transferId]);
        $transNum = $stmt->fetchColumn();
        log_audit($userId, 'cancel', 'transfer', $transferId, 'إلغاء أمر تحويل مخزني رقم ' . ($transNum ?: ('#' . $transferId)));
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function receive_transfer(int $transferId, int $userId, ?array $fromTypes = null, ?array $toTypes = null): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM inventory_transfers WHERE id = ? FOR UPDATE');
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch();
        if (!$transfer || $transfer['status'] !== 'pending') {
            throw new RuntimeException('أمر التحويل غير متاح للاستلام.');
        }
        if ($fromTypes !== null && $toTypes !== null) {
            require_transfer_location_types($db, $transfer, $fromTypes, $toTypes);
        }
        require_location_access((int) $transfer['to_location_id']);
        $stmt = $db->prepare('SELECT * FROM inventory_transfer_items WHERE transfer_id = ?');
        $stmt->execute([$transferId]);
        foreach ($stmt->fetchAll() as $item) {
            move_inventory($db, (int) $transfer['to_location_id'], (int) $item['product_id'], (float) $item['quantity'], 'transfer_future', $userId, 'transfer', $transferId, 'استلام تحويل مخزون');
        }
        $stmt = $db->prepare('UPDATE inventory_transfers SET status = "received", received_by = ?, received_at = NOW() WHERE id = ?');
        $stmt->execute([$userId, $transferId]);
        
        $stmtSelect = $db->prepare('SELECT transfer_number FROM inventory_transfers WHERE id = ?');
        $stmtSelect->execute([$transferId]);
        $transNum = $stmtSelect->fetchColumn();
        log_audit($userId, 'receive', 'transfer', $transferId, 'استلام شحنة تحويل رقم ' . ($transNum ?: ('#' . $transferId)));
        
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function create_supply_transfer(array $data, int $userId): void
{
    validate_supply_transfer($data);
    if (!transfer_line_items($data)) {
        throw new RuntimeException('يجب إضافة صنف واحد على الأقل في التحويل.');
    }
    create_transfer($data, $userId);
}

function create_branch_transfer(array $data, int $userId): void
{
    validate_branch_transfer($data);
    if (!transfer_line_items($data)) {
        throw new RuntimeException('يجب إضافة صنف واحد على الأقل في التحويل.');
    }
    create_transfer($data, $userId);
}

function update_supply_transfer(array $data, int $userId): void
{
    validate_supply_transfer($data);
    update_transfer($data, $userId, ['warehouse'], ['branch']);
}

function update_branch_transfer(array $data, int $userId): void
{
    validate_branch_transfer($data);
    update_transfer($data, $userId, ['branch'], ['branch']);
}

function transfers_rows(?int $locationId = null): array
{
    $sql = 'SELECT t.*, f.name AS from_name, tl.name AS to_name, u.name AS created_name, r.name AS received_name 
            FROM inventory_transfers t 
            JOIN locations f ON f.id = t.from_location_id 
            JOIN locations tl ON tl.id = t.to_location_id 
            JOIN users u ON u.id = t.created_by 
            LEFT JOIN users r ON r.id = t.received_by';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE t.from_location_id = ? OR t.to_location_id = ?';
        $params[] = $locationId;
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function supply_transfers_rows(?int $locationId = null, array $filters = []): array
{
    $sql = "
        SELECT t.*, 
               fl.name AS from_name, 
               tl.name AS to_name,
               uc.name AS created_name,
               ur.name AS received_name
        FROM inventory_transfers t
        JOIN locations fl ON fl.id = t.from_location_id
        JOIN locations tl ON tl.id = t.to_location_id
        LEFT JOIN users uc ON uc.id = t.created_by
        LEFT JOIN users ur ON ur.id = t.received_by
        WHERE fl.type = 'warehouse' AND tl.type = 'branch'
    ";
    $params = [];
    if ($locationId !== null) {
        $sql .= ' AND (t.from_location_id = ? OR t.to_location_id = ?)';
        $params[] = $locationId;
        $params[] = $locationId;
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND t.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['method'])) {
        $sql .= ' AND t.method = ?';
        $params[] = $filters['method'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND t.transfer_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND t.transfer_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['method'])) {
        $sql .= ' AND t.method = ?';
        $params[] = $filters['method'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND t.transfer_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND t.transfer_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['from_location_id'])) {
        $sql .= ' AND t.from_location_id = ?';
        $params[] = $filters['from_location_id'];
    }
    if (!empty($filters['to_location_id'])) {
        $sql .= ' AND t.to_location_id = ?';
        $params[] = $filters['to_location_id'];
    }
    if (!empty($filters['q'])) {
        $q = '%' . trim($filters['q']) . '%';
        $sql .= ' AND (t.transfer_number LIKE ? OR fl.name LIKE ? OR tl.name LIKE ? OR t.sender_name LIKE ? OR t.receiver_name LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND t.transfer_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND t.transfer_date <= ?';
        $params[] = $filters['date_to'];
    }
    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function branch_transfers_rows(?int $locationId = null, array $filters = []): array
{
    $sql = "
        SELECT t.*, 
               fl.name AS from_name, 
               tl.name AS to_name,
               uc.name AS created_name,
               ur.name AS received_name
        FROM inventory_transfers t
        JOIN locations fl ON fl.id = t.from_location_id
        JOIN locations tl ON tl.id = t.to_location_id
        LEFT JOIN users uc ON uc.id = t.created_by
        LEFT JOIN users ur ON ur.id = t.received_by
        WHERE fl.type = 'branch' AND tl.type = 'branch'
    ";
    $params = [];
    if ($locationId !== null) {
        $sql .= ' AND (t.from_location_id = ? OR t.to_location_id = ?)';
        $params[] = $locationId;
        $params[] = $locationId;
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND t.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['from_location_id'])) {
        $sql .= ' AND t.from_location_id = ?';
        $params[] = $filters['from_location_id'];
    }
    if (!empty($filters['to_location_id'])) {
        $sql .= ' AND t.to_location_id = ?';
        $params[] = $filters['to_location_id'];
    }
    if (!empty($filters['q'])) {
        $q = '%' . trim($filters['q']) . '%';
        $sql .= ' AND (t.transfer_number LIKE ? OR fl.name LIKE ? OR tl.name LIKE ? OR t.sender_name LIKE ? OR t.receiver_name LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND t.transfer_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND t.transfer_date <= ?';
        $params[] = $filters['date_to'];
    }
    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_transfer(int $transferId): ?array
{
    $stmt = pdo()->prepare('SELECT t.*, f.name AS from_name, tl.name AS to_name, u.name AS created_name, r.name AS received_name 
                            FROM inventory_transfers t 
                            JOIN locations f ON f.id = t.from_location_id 
                            JOIN locations tl ON tl.id = t.to_location_id 
                            JOIN users u ON u.id = t.created_by 
                            LEFT JOIN users r ON r.id = t.received_by 
                            WHERE t.id = ?');
    $stmt->execute([$transferId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

function get_transfer_items(int $transferId): array
{
    $stmt = pdo()->prepare('SELECT iti.*, p.name AS product_name, p.unit AS product_unit
                            FROM inventory_transfer_items iti
                            JOIN products p ON p.id = iti.product_id
                            WHERE iti.transfer_id = ?');
    $stmt->execute([$transferId]);
    return $stmt->fetchAll();
}

function create_return_invoice(int $invoiceId, string $method, string $reason, int $userId, float $refundPaid = -1): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new RuntimeException('الفاتورة غير موجودة.');
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM return_invoices WHERE original_invoice_id = ?');
        $stmt->execute([$invoiceId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('تم عمل مرتجع لهذه الفاتورة من قبل.');
        }
        $amount = (float) $invoice['total'];
        if ($refundPaid < 0) $refundPaid = $amount; // default: full refund
        $number = 'RT-' . date('Ymd-His') . '-' . $invoiceId;
        $stmt = $db->prepare('INSERT INTO return_invoices (original_invoice_id, return_number, refund_method, amount, refund_paid, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$invoiceId, $number, $method, $amount, $refundPaid, $reason ?: null, $userId]);
        $returnId = (int) $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM invoice_line_components WHERE invoice_line_id IN (SELECT id FROM invoice_lines WHERE invoice_id = ?)');
        $stmt->execute([$invoiceId]);
        foreach ($stmt->fetchAll() as $component) {
            move_inventory($db, (int) $invoice['location_id'], (int) $component['component_product_id'], (float) $component['quantity'], 'return_future', $userId, 'return', $returnId, 'مرتجع فاتورة');
        }
        $stmt = $db->prepare('UPDATE invoices SET status = "void_future" WHERE id = ?');
        $stmt->execute([$invoiceId]);
        // Log cash out in financial_ledger
        if (in_array($method, ['cash', 'instapay', 'vodafone_cash']) && $refundPaid > 0) {
            $ledgerMethod = in_array($method, ['cash', 'instapay', 'vodafone_cash']) ? $method : 'cash';
            $stmt = $db->prepare("INSERT INTO financial_ledger (location_id, direction, method, amount, source_type, source_id, description, created_by) VALUES (?, 'out', ?, ?, 'return', ?, ?, ?)");
            $stmt->execute([(int)$invoice['location_id'], $ledgerMethod, $refundPaid, $returnId, 'مرتجع فاتورة ' . ($invoice['invoice_number'] ?? ''), $userId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_invoice_line_components(int $lineId): array
{
    $stmt = pdo()->prepare('
        SELECT c.*, p.name, p.type, p.price_per_gram, p.sale_price 
        FROM invoice_line_components c 
        JOIN products p ON p.id = c.component_product_id 
        WHERE c.invoice_line_id = ?
    ');
    $stmt->execute([$lineId]);
    return $stmt->fetchAll();
}

function get_off_order_formulas(?int $locationId = null): array
{
    $db = pdo();
    $sql = "
        SELECT il.*, i.invoice_number, i.location_id, i.created_at AS invoice_date, l.name AS location_name
        FROM invoice_lines il
        JOIN invoices i ON i.id = il.invoice_id
        LEFT JOIN locations l ON l.id = i.location_id
        WHERE il.line_type IN ('custom_recipe', 'saved_recipe')
          AND (
              i.status = 'void_future'
              OR EXISTS (
                  SELECT 1 FROM return_invoices ri 
                  WHERE ri.original_invoice_id = i.id 
                    AND ri.return_number LIKE CONCAT('RT-L-%-', il.id)
              )
          )
    ";
    $params = [];
    if ($locationId) {
        $sql .= " AND i.location_id = ?";
        $params[] = $locationId;
    }
    $sql .= " ORDER BY i.created_at DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function recent_returnable_lines(): array
{
    return pdo()->query("SELECT il.id AS line_id, il.description, il.line_total, i.invoice_number, i.created_at, c.name AS customer_name FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id LEFT JOIN customers c ON c.id = i.customer_id WHERE i.status = 'completed' ORDER BY i.created_at DESC, il.id DESC LIMIT 150")->fetchAll();
}

function create_return_line_invoice(int $lineId, string $method, string $reason, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT il.*, i.location_id, i.invoice_number FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id WHERE il.id = ? FOR UPDATE');
        $stmt->execute([$lineId]);
        $line = $stmt->fetch();
        if (!$line) {
            throw new RuntimeException('بند الفاتورة غير موجود.');
        }
        $number = 'RT-L-' . date('Ymd-His') . '-' . $lineId;
        $stmt = $db->prepare('INSERT INTO return_invoices (original_invoice_id, return_number, refund_method, amount, reason, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$line['invoice_id'], $number, $method, $line['line_total'], $reason ?: ('مرتجع بند: ' . $line['description']), $userId]);
        $returnId = (int) $db->lastInsertId();

        $stmt = $db->prepare('SELECT * FROM invoice_line_components WHERE invoice_line_id = ?');
        $stmt->execute([$lineId]);
        foreach ($stmt->fetchAll() as $component) {
            move_inventory($db, (int) $line['location_id'], (int) $component['component_product_id'], (float) $component['quantity'], 'return_future', $userId, 'return', $returnId, 'مرتجع بند فاتورة');
        }
        log_audit($userId, 'return_line', 'invoice_line', $lineId, 'مرتجع بند من فاتورة ' . $line['invoice_number']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Return one or more selected lines from a single invoice.
 * If ALL lines are selected the whole invoice is voided; otherwise only the
 * selected lines have their inventory restored and a partial return is logged.
 */
function create_return_selected_lines(int $invoiceId, array $lineIds, array $returnedQuantities, string $method, string $reason, int $userId, float $refundPaid = -1): void
{
    if (empty($lineIds)) {
        throw new RuntimeException('لم يتم تحديد أي أصناف للإرجاع.');
    }

    $db = pdo();
    $db->beginTransaction();
    try {
        /* Lock invoice */
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) throw new RuntimeException('الفاتورة غير موجودة.');

        /* Fetch ALL lines of invoice */
        $stmt = $db->prepare('SELECT id, quantity, line_total FROM invoice_lines WHERE invoice_id = ?');
        $stmt->execute([$invoiceId]);
        $allLines = $stmt->fetchAll();
        $allLineIds = array_column($allLines, 'id');

        // Validate requested IDs belong to this invoice
        $validIds = array_intersect(array_map('intval', $lineIds), array_map('intval', $allLineIds));
        if (empty($validIds)) throw new RuntimeException('بنود الإرجاع لا تنتمي لهذه الفاتورة.');

        /* Sum selected lines based on proportional returned quantities */
        $returnAmount = 0.0;
        $proportions = [];
        $lineReturnedQtys = [];

        foreach ($allLines as $l) {
            $lineId = (int)$l['id'];
            if (in_array($lineId, $validIds)) {
                $originalQty = (float)$l['quantity'];
                
                // Get returned quantity from POST, default to original quantity if missing
                $returnedQty = isset($returnedQuantities[$lineId]) ? (float)$returnedQuantities[$lineId] : $originalQty;
                if ($returnedQty <= 0) {
                    throw new RuntimeException('الكمية المرتجعة يجب أن تكون أكبر من صفر.');
                }
                if ($returnedQty > $originalQty) {
                    throw new RuntimeException('الكمية المرتجعة لا يمكن أن تتجاوز الكمية الأصلية.');
                }
                
                $proportion = $originalQty > 0 ? ($returnedQty / $originalQty) : 1.0;
                $proportions[$lineId] = $proportion;
                $lineReturnedQtys[$lineId] = $returnedQty;
                
                $returnAmount += (float)$l['line_total'] * $proportion;
            }
        }

        if ($refundPaid < 0) $refundPaid = $returnAmount; // default: full refund

        /* Check if this is a complete full return of the entire invoice */
        $isFullReturn = (count($validIds) === count($allLineIds));
        if ($isFullReturn) {
            foreach ($allLines as $l) {
                $lineId = (int)$l['id'];
                $originalQty = (float)$l['quantity'];
                $returnedQty = $lineReturnedQtys[$lineId] ?? 0.0;
                if (abs($returnedQty - $originalQty) > 0.0001) {
                    $isFullReturn = false;
                    break;
                }
            }
        }

        /* Create return record */
        $number = 'RT-' . date('Ymd-His') . '-' . $invoiceId;
        $stmt = $db->prepare('INSERT INTO return_invoices (original_invoice_id, return_number, refund_method, amount, refund_paid, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$invoiceId, $number, $method, $returnAmount, $refundPaid, $reason ?: null, $userId]);
        $returnId = (int)$db->lastInsertId();

        /* Restore inventory for each selected line's components proportionally */
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $stmt = $db->prepare("SELECT * FROM invoice_line_components WHERE invoice_line_id IN ($placeholders)");
        $stmt->execute(array_values($validIds));
        foreach ($stmt->fetchAll() as $component) {
            $lineId = (int)$component['invoice_line_id'];
            $proportion = $proportions[$lineId] ?? 1.0;
            $restoreQty = (float)$component['quantity'] * $proportion;
            
            move_inventory($db, (int)$invoice['location_id'], (int)$component['component_product_id'], $restoreQty, 'return_future', $userId, 'return', $returnId, 'مرتجع جزئي لبنود فاتورة');
        }

        /* If full return: void the invoice */
        if ($isFullReturn) {
            $stmt = $db->prepare('UPDATE invoices SET status = "void_future" WHERE id = ?');
            $stmt->execute([$invoiceId]);
        }

        /* Log financial_ledger cash out */
        if (in_array($method, ['cash', 'instapay', 'vodafone_cash']) && $refundPaid > 0) {
            $stmt = $db->prepare("INSERT INTO financial_ledger (location_id, direction, method, amount, source_type, source_id, description, created_by) VALUES (?, 'out', ?, ?, 'return', ?, ?, ?)");
            $stmt->execute([(int)$invoice['location_id'], $method, $refundPaid, $returnId, 'مرتجع ' . ($invoice['invoice_number'] ?? ''), $userId]);
        }

        log_audit($userId, 'return_lines', 'invoice', $invoiceId, sprintf('مرتجع جزئي لعدد %d بند(اً) من فاتورة %s — المبلغ: %.2f — المدفوع: %.2f', count($validIds), $invoice['invoice_number'], $returnAmount, $refundPaid));

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_return_paid(int $returnId, float $newPaid, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT r.*, i.location_id, i.invoice_number FROM return_invoices r JOIN invoices i ON i.id = r.original_invoice_id WHERE r.id = ? FOR UPDATE');
        $stmt->execute([$returnId]);
        $ret = $stmt->fetch();
        if (!$ret) throw new RuntimeException('سجل المرتجع غير موجود.');

        $oldPaid = (float)$ret['refund_paid'];
        $diff    = $newPaid - $oldPaid;

        // Update refund_paid
        $stmt = $db->prepare('UPDATE return_invoices SET refund_paid = ? WHERE id = ?');
        $stmt->execute([$newPaid, $returnId]);

        // Adjust financial_ledger: delete old entry, insert new
        $method = in_array($ret['refund_method'], ['cash','instapay','vodafone_cash']) ? $ret['refund_method'] : 'cash';
        if (in_array($ret['refund_method'], ['cash','instapay','vodafone_cash'])) {
            $stmt = $db->prepare("DELETE FROM financial_ledger WHERE source_type = 'return' AND source_id = ?");
            $stmt->execute([$returnId]);
            if ($newPaid > 0) {
                $stmt = $db->prepare("INSERT INTO financial_ledger (location_id, direction, method, amount, source_type, source_id, description, created_by) VALUES (?, 'out', ?, ?, 'return', ?, ?, ?)");
                $stmt->execute([(int)$ret['location_id'], $method, $newPaid, $returnId, 'مرتجع ' . $ret['invoice_number'], $userId]);
            }
        }

        log_audit($userId, 'update_return_paid', 'return_invoices', $returnId, sprintf('تعديل مبلغ الرد: %.2f ← %.2f', $oldPaid, $newPaid));
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function returns_rows(array $filters = []): array
{
    $db = pdo();
    $sql = 'SELECT r.*, i.invoice_number, i.location_id, i.customer_id, l.name AS location_name, c.name AS customer_name, u.name AS user_name 
            FROM return_invoices r 
            JOIN invoices i ON i.id = r.original_invoice_id 
            JOIN locations l ON l.id = i.location_id
            LEFT JOIN customers c ON c.id = i.customer_id
            JOIN users u ON u.id = r.created_by';
    
    $where = [];
    $params = [];
    
    if (!empty($filters['location_id'])) {
        $where[] = 'i.location_id = ?';
        $params[] = (int)$filters['location_id'];
    }
    if (!empty($filters['customer_id'])) {
        $where[] = 'i.customer_id = ?';
        $params[] = (int)$filters['customer_id'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(r.return_number LIKE ? OR i.invoice_number LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['start_date'])) {
        $where[] = 'DATE(r.created_at) >= ?';
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $where[] = 'DATE(r.created_at) <= ?';
        $params[] = $filters['end_date'];
    }
    
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $sql .= ' ORDER BY r.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_last_shift_closure_time(int $locationId): string
{
    $db = pdo();
    $stmt = $db->prepare('SELECT created_at FROM shift_closures WHERE location_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$locationId]);
    return $stmt->fetchColumn() ?: '1970-01-01 00:00:00';
}

/**
 * Get all invoices created during a shift period for a location
 */
function shift_invoices(int $locationId, string $startTime, string $endTime): array
{
    $stmt = pdo()->prepare("SELECT i.*, u.name AS user_name, c.name AS customer_name,
        (SELECT GROUP_CONCAT(DISTINCT p.method SEPARATOR ', ') FROM payments p WHERE p.invoice_id = i.id) AS payment_methods
        FROM invoices i 
        JOIN users u ON u.id = i.user_id 
        LEFT JOIN customers c ON c.id = i.customer_id 
        WHERE i.location_id = ? AND i.created_at >= ? AND i.created_at <= ? 
        ORDER BY i.created_at");
    $stmt->execute([$locationId, $startTime, $endTime]);
    return $stmt->fetchAll();
}

/**
 * Get payment totals grouped by method for a shift period
 */
function shift_payment_totals(int $locationId, string $startTime, string $endTime): array
{
    $stmt = pdo()->prepare("SELECT p.method, COALESCE(SUM(p.amount), 0) AS total 
        FROM payments p 
        JOIN invoices i ON i.id = p.invoice_id 
        WHERE i.location_id = ? AND p.created_at >= ? AND p.created_at <= ?
        GROUP BY p.method");
    $stmt->execute([$locationId, $startTime, $endTime]);
    $result = ['cash' => 0.0, 'instapay' => 0.0, 'vodafone_cash' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        $result[(string)$row['method']] = (float)$row['total'];
    }
    return $result;
}

/**
 * Get cash returns for a shift period
 */
function shift_cash_returns(int $locationId, string $startTime, string $endTime): float
{
    $stmt = pdo()->prepare("SELECT COALESCE(SUM(r.refund_paid), 0) FROM return_invoices r 
        JOIN invoices i ON i.id = r.original_invoice_id 
        WHERE i.location_id = ? AND r.refund_method = 'cash' AND r.created_at >= ? AND r.created_at <= ?");
    $stmt->execute([$locationId, $startTime, $endTime]);
    return (float)$stmt->fetchColumn();
}

/**
 * Get cash expenses for a shift period
 */
function shift_cash_expenses(int $locationId, string $startTime, string $endTime): float
{
    $stmt = pdo()->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses 
        WHERE location_id = ? AND payment_method = 'cash' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$locationId, $startTime, $endTime]);
    return (float)$stmt->fetchColumn();
}

/**
 * Get debt payment totals grouped by method for a shift period
 * Returns amounts paid toward customer debts (payment_type = 'debt_payment')
 */
function shift_debt_payments(int $locationId, string $startTime, string $endTime): array
{
    $stmt = pdo()->prepare("SELECT p.method, COALESCE(SUM(p.amount), 0) AS total
        FROM payments p
        JOIN invoices i ON i.id = p.invoice_id
        WHERE i.location_id = ? 
          AND p.payment_type = 'debt_payment'
          AND p.created_at >= ? AND p.created_at <= ?
        GROUP BY p.method");
    $stmt->execute([$locationId, $startTime, $endTime]);
    $result = ['cash' => 0.0, 'instapay' => 0.0, 'vodafone_cash' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        $result[(string)$row['method']] = (float)$row['total'];
    }
    return $result;
}

/**
 * Get attendance records for a location during a shift period with user names
 */
function shift_attendance_records(int $locationId, string $startTime, string $endTime): array
{
    $stmt = pdo()->prepare("SELECT a.*, u.name AS user_name 
        FROM attendance_records a 
        JOIN users u ON u.id = a.user_id 
        WHERE a.location_id = ? AND a.created_at >= ? AND a.created_at <= ? 
        ORDER BY a.created_at");
    $stmt->execute([$locationId, $startTime, $endTime]);
    return $stmt->fetchAll();
}

/**
 * Get the first check-in record for a location in a period (considered shift start)
 */
function shift_first_checkin(int $locationId, string $fromTime): ?array
{
    $stmt = pdo()->prepare("SELECT a.*, u.name AS user_name 
        FROM attendance_records a 
        JOIN users u ON u.id = a.user_id 
        WHERE a.location_id = ? AND a.action = 'check_in' AND a.created_at >= ? 
        ORDER BY a.created_at LIMIT 1");
    $stmt->execute([$locationId, $fromTime]);
    return $stmt->fetch() ?: null;
}

/**
 * Get the first invoice created for a location in a period
 */
function shift_first_invoice(int $locationId, string $fromTime): ?array
{
    $stmt = pdo()->prepare("SELECT i.*, u.name AS user_name 
        FROM invoices i 
        JOIN users u ON u.id = i.user_id 
        WHERE i.location_id = ? AND i.created_at >= ? 
        ORDER BY i.created_at LIMIT 1");
    $stmt->execute([$locationId, $fromTime]);
    return $stmt->fetch() ?: null;
}

/**
 * Get employees who attended during the shift with their commission calculations
 * Uses the user's commission_percent and calculates based on their individual sales during the period
 */
function shift_employee_commissions(int $locationId, string $startTime, string $endTime): array
{
    $db = pdo();
    // Get distinct users who attended
        $stmt = $db->prepare("SELECT DISTINCT a.user_id, u.name AS user_name, u.commission_percent 
        FROM attendance_records a 
        JOIN users u ON u.id = a.user_id 
        WHERE a.location_id = ? AND a.created_at >= ? AND a.created_at <= ?
        ORDER BY u.name");
    $stmt->execute([$locationId, $startTime, $endTime]);
    $employees = $stmt->fetchAll();
    
    $result = [];
    foreach ($employees as $emp) {
        $userId = (int)$emp['user_id'];
        $commPercent = (float)$emp['commission_percent'];
        
        // Get sales by this employee during their attendance intervals
        $sales = employee_sales_during_attendance($userId, $startTime, $endTime, $locationId);
        $commission = $sales * ($commPercent / 100);
        
        // Get attendance intervals
        $intervals = attendance_intervals_for_user($userId, $startTime, $endTime, $locationId);
        $totalMinutes = 0;
        foreach ($intervals as $interval) {
            $totalMinutes += $interval['minutes'];
        }
        
        // Get first check_in and last check_out times
        $checkStmt = $db->prepare("SELECT MIN(CASE WHEN action='check_in' THEN created_at END) AS first_in,
            MAX(CASE WHEN action='check_out' THEN created_at END) AS last_out
            FROM attendance_records 
            WHERE user_id = ? AND location_id = ? AND created_at >= ? AND created_at <= ?");
        $checkStmt->execute([$userId, $locationId, $startTime, $endTime]);
        $times = $checkStmt->fetch();
        
        $result[] = [
            'user_id' => $userId,
            'user_name' => $emp['user_name'],
            'commission_percent' => $commPercent,
            'sales' => $sales,
            'commission' => $commission,
            'total_minutes' => $totalMinutes,
            'first_check_in' => $times['first_in'] ?? null,
            'last_check_out' => $times['last_out'] ?? null,
        ];
    }
    
    return $result;
}

/**
 * Enhanced close_shift function that stores detailed shift data and handles cash transfer
 * 
 * @param int $locationId
 * @param float $actualCash Actual cash counted in drawer
 * @param string $notes
 * @param array $user Current user
 * @param string $cashTransferAction 'all', 'partial', or 'none'
 * @param float $cashTransferredAmount Amount to transfer to manager (0 if none)
 * @return int The new shift closure ID
 */
function close_shift_with_details(int $locationId, float $actualCash, string $notes, array $user, string $cashTransferAction = 'none', float $cashTransferredAmount = 0): int
{
    require_location_access($locationId);
    require_location_type($locationId, ['branch', 'warehouse'], 'إغلاق الشيفت مسموح للفروع والمخزن الرئيسي فقط.');
    
    $db = pdo();
    $db->beginTransaction();
    
    try {
        $shiftStart = get_last_shift_closure_time($locationId);
        $now = date('Y-m-d H:i:s');
        
        // Determine actual shift start: first check-in, or first invoice, or last closure
        $firstCheckin = shift_first_checkin($locationId, $shiftStart);
        $firstInvoice = shift_first_invoice($locationId, $shiftStart);
        
        if ($firstCheckin) {
            $actualShiftStart = $firstCheckin['created_at'];
            $shiftStartUserId = (int)$firstCheckin['user_id'];
        } elseif ($firstInvoice) {
            $actualShiftStart = $firstInvoice['created_at'];
            $shiftStartUserId = (int)$firstInvoice['user_id'];
        } else {
            $actualShiftStart = $shiftStart;
            $shiftStartUserId = null;
        }
        
        // Get payment totals using the original boundary (last closure)
        $paymentTotals = shift_payment_totals($locationId, $shiftStart, $now);
        $totalCashSales = $paymentTotals['cash'];
        $totalInstapay = $paymentTotals['instapay'];
        $totalVodafoneCash = $paymentTotals['vodafone_cash'];
        
        // Get returns and expenses
        $totalReturnsCash = shift_cash_returns($locationId, $shiftStart, $now);
        $totalExpensesCash = shift_cash_expenses($locationId, $shiftStart, $now);
        
        // Get invoice count
        $invStmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE location_id = ? AND created_at >= ? AND created_at <= ?");
        $invStmt->execute([$locationId, $shiftStart, $now]);
        $totalInvoices = (int)$invStmt->fetchColumn();
        
        // Calculate expected and net cash
        $expectedCash = $totalCashSales - $totalReturnsCash - $totalExpensesCash;
        $netCash = $expectedCash;
        
        // Check if cash transfer action is valid
        $validActions = ['all', 'partial', 'none'];
        if (!in_array($cashTransferAction, $validActions, true)) {
            $cashTransferAction = 'none';
        }
        
        $cashTransferAmt = 0.0;
        $cashRemaining = $actualCash;
        
        if ($cashTransferAction === 'all') {
            $cashTransferAmt = $actualCash;
            $cashRemaining = 0;
        } elseif ($cashTransferAction === 'partial') {
            $cashTransferAmt = abs($cashTransferredAmount);
            if ($cashTransferAmt > $actualCash) {
                $cashTransferAmt = $actualCash;
            }
            $cashRemaining = $actualCash - $cashTransferAmt;
        }
        
        $difference = $actualCash - $expectedCash;
        
        // Insert shift closure with all details
        $stmt = $db->prepare('INSERT INTO shift_closures 
            (user_id, location_id, shift_date, shift_start_time, shift_start_user_id, 
             expected_cash, actual_cash, difference, 
             total_cash_sales, total_instapay, total_vodafone_cash, 
             total_returns_cash, total_expenses_cash, net_cash, total_invoices,
             cash_transferred, cash_transfer_action, cash_remaining_in_drawer,
             notes, created_at) 
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $stmt->execute([
            (int)$user['id'],
            $locationId,
            $actualShiftStart !== '1970-01-01 00:00:00' ? $actualShiftStart : null,
            $shiftStartUserId,
            $expectedCash,
            $actualCash,
            $difference,
            $totalCashSales,
            $totalInstapay,
            $totalVodafoneCash,
            $totalReturnsCash,
            $totalExpensesCash,
            $netCash,
            $totalInvoices,
            $cashTransferAmt,
            $cashTransferAction,
            $cashRemaining,
            $notes ?: null,
            $now,
        ]);
        
        $closureId = (int)$db->lastInsertId();
        
        // If transferring cash to manager, create branch cash transfer + manager collection
        if ($cashTransferAmt > 0) {
            $transferNumber = 'SHIFT-' . $closureId . '-' . date('YmdHis');
            $stmt = $db->prepare('INSERT INTO branch_cash_transfers 
                (transfer_number, location_id, method, amount, transfer_date, status, notes, created_by, created_at) 
                VALUES (?, ?, "cash", ?, CURDATE(), "pending", ?, ?, ?)');
            $stmt->execute([
                $transferNumber,
                $locationId,
                $cashTransferAmt,
                'تحويل كاش من إغلاق الشيفت #' . $closureId . ' - ' . ($notes ?: ''),
                (int)$user['id'],
                $now,
            ]);
            $transferId = (int)$db->lastInsertId();
            
            // Create manager collection entry
            upsert_branch_transfer_manager_collection([
                'id' => $transferId,
                'location_id' => $locationId,
                'method' => 'cash',
                'amount' => $cashTransferAmt,
                'transfer_date' => date('Y-m-d'),
                'status' => 'pending',
                'notes' => 'تحويل كاش من إغلاق الشيفت #' . $closureId,
                'created_by' => (int)$user['id'],
            ]);
            
            log_audit((int)$user['id'], 'create', 'branch_cash_transfer', $transferId, 
                'تحويل كاش من الشيفت: ' . $cashTransferAmt . ' ج.م من ' . ($firstCheckin['user_name'] ?? '') . ' - شيفت #' . $closureId);
        }
        
        log_audit((int)$user['id'], 'close_shift', 'shift_closures', $closureId, 
            'إغلاق شيفت بقيمة ' . $actualCash . ' ج.م (متوقع: ' . $expectedCash . ' ج.م) - تحويل: ' . $cashTransferAction . ' - المبلغ: ' . $cashTransferAmt . ' ج.م');
        
        $db->commit();
        return $closureId;
        
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Automatically check and close active shifts that crossed the 6:00 AM boundary
 */
function auto_close_shifts_if_needed(): void
{
    $db = pdo();
    // Get all active branch locations
    try {
        $locations = $db->query("SELECT id FROM locations WHERE type = 'branch' AND is_active = 1")->fetchAll();
    } catch (Throwable $e) {
        return; // DB not installed yet or error
    }

    foreach ($locations as $loc) {
        $locationId = (int)$loc['id'];

        while (true) {
            $lastClosure = get_last_shift_closure_time($locationId);

            // Determine actual shift start based on check-ins or invoices since last closure
            $firstCheckin = shift_first_checkin($locationId, $lastClosure);
            $firstInvoice = shift_first_invoice($locationId, $lastClosure);

            if ($firstCheckin && $firstInvoice) {
                $shiftStart = ($firstCheckin['created_at'] < $firstInvoice['created_at'])
                    ? $firstCheckin['created_at']
                    : $firstInvoice['created_at'];
                $shiftStartUserId = ($firstCheckin['created_at'] < $firstInvoice['created_at'])
                    ? (int)$firstCheckin['user_id']
                    : (int)$firstInvoice['user_id'];
            } elseif ($firstCheckin) {
                $shiftStart = $firstCheckin['created_at'];
                $shiftStartUserId = (int)$firstCheckin['user_id'];
            } elseif ($firstInvoice) {
                $shiftStart = $firstInvoice['created_at'];
                $shiftStartUserId = (int)$firstInvoice['user_id'];
            } else {
                // No activity since last closure, nothing to close
                break;
            }

            // Calculate first 6:00 AM boundary after $shiftStart
            $startDT = new DateTime($shiftStart);
            $boundaryDT = clone $startDT;
            $boundaryDT->setTime(6, 0, 0);
            if ($startDT >= $boundaryDT) {
                $boundaryDT->modify('+1 day');
            }

            $nowDT = new DateTime();
            if ($nowDT <= $boundaryDT) {
                // Boundary has not passed yet, shift is still active
                break;
            }

            // Boundary has passed. Auto close the shift at the boundary time.
            $boundaryStr = $boundaryDT->format('Y-m-d H:i:s');
            auto_close_shift_at_time($locationId, $lastClosure, $boundaryStr, $shiftStart, $shiftStartUserId);
        }
    }
}

/**
 * Helper to auto close a single shift at the boundary time
 */
function auto_close_shift_at_time(int $locationId, string $startTime, string $endTime, string $actualShiftStart, ?int $shiftStartUserId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $paymentTotals = shift_payment_totals($locationId, $startTime, $endTime);
        $totalCashSales = $paymentTotals['cash'];
        $totalInstapay = $paymentTotals['instapay'];
        $totalVodafoneCash = $paymentTotals['vodafone_cash'];

        $totalReturnsCash = shift_cash_returns($locationId, $startTime, $endTime);
        $totalExpensesCash = shift_cash_expenses($locationId, $startTime, $endTime);

        $invStmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE location_id = ? AND created_at >= ? AND created_at <= ?");
        $invStmt->execute([$locationId, $startTime, $endTime]);
        $totalInvoices = (int)$invStmt->fetchColumn();

        $expectedCash = $totalCashSales - $totalReturnsCash - $totalExpensesCash;
        if ($expectedCash < 0) {
            $expectedCash = 0.0;
        }

        $userId = $shiftStartUserId ?: 1; // Default to admin or first check-in user
        $notes = 'إغلاق تلقائي (تجاوز الساعة 6 صباحاً)';

        $stmt = $db->prepare('INSERT INTO shift_closures 
            (user_id, location_id, shift_date, shift_start_time, shift_start_user_id, 
             expected_cash, actual_cash, difference, 
             total_cash_sales, total_instapay, total_vodafone_cash, 
             total_returns_cash, total_expenses_cash, net_cash, total_invoices,
             cash_transferred, cash_transfer_action, cash_remaining_in_drawer,
             notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            $userId,
            $locationId,
            date('Y-m-d', strtotime($endTime)),
            $actualShiftStart,
            $shiftStartUserId,
            $expectedCash,
            $expectedCash, // actual_cash = expected_cash
            0.00, // difference = 0
            $totalCashSales,
            $totalInstapay,
            $totalVodafoneCash,
            $totalReturnsCash,
            $totalExpensesCash,
            $expectedCash, // net_cash
            $totalInvoices,
            0.00, // cash_transferred = 0
            'none', // cash_transfer_action = none
            $expectedCash, // cash_remaining_in_drawer = expected
            $notes,
            $endTime
        ]);

        $closureId = (int)$db->lastInsertId();
        log_audit($userId, 'auto_close_shift', 'shift_closures', $closureId, 
            'إغلاق تلقائي لشيفت الفرع رقم ' . $locationId . ' بقيمة ' . $expectedCash . ' ج.م');

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}


/**
 * Get detailed shift data for viewing
 */
function get_shift_details(int $shiftId): ?array
{
    $stmt = pdo()->prepare('SELECT s.*, u.name AS user_name, l.name AS location_name,
        su.name AS shift_start_user_name
        FROM shift_closures s 
        JOIN users u ON u.id = s.user_id 
        JOIN locations l ON l.id = s.location_id 
        LEFT JOIN users su ON su.id = s.shift_start_user_id 
        WHERE s.id = ?');
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch();
    if (!$shift) return null;
    
    $locationId = (int)$shift['location_id'];
    $startTime = $shift['shift_start_time'] ?? '1970-01-01 00:00:00';
    $endTime = $shift['created_at'];
    
    $shift['invoices'] = shift_invoices($locationId, $startTime, $endTime);
    $shift['attendance'] = shift_attendance_records($locationId, $startTime, $endTime);
    $shift['commissions'] = shift_employee_commissions($locationId, $startTime, $endTime);
    
    return $shift;
}

/**
 * Get shift closures with filters for the record page
 */
function shift_rows_filtered(array $filters): array
{
    $sql = 'SELECT s.*, u.name AS user_name, l.name AS location_name 
        FROM shift_closures s 
        JOIN users u ON u.id = s.user_id 
        JOIN locations l ON l.id = s.location_id 
        WHERE 1=1';
    $params = [];
    
    if (!empty($filters['location_id'])) {
        $sql .= ' AND s.location_id = ?';
        $params[] = (int)$filters['location_id'];
    }
    
    if (!empty($filters['user_id'])) {
        $sql .= ' AND s.user_id = ?';
        $params[] = (int)$filters['user_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= ' AND DATE(s.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= ' AND DATE(s.created_at) <= ?';
        $params[] = $filters['date_to'];
    }
    
    $sql .= ' ORDER BY s.created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get all users who can close shifts (for filter dropdown)
 */
function shift_users(): array
{
    return pdo()->query("SELECT DISTINCT u.id, u.name FROM shift_closures s JOIN users u ON u.id = s.user_id ORDER BY u.name")->fetchAll();
}

function expected_cash_for(int $locationId): float
{
    $db = pdo();
    $lastClosure = get_last_shift_closure_time($locationId);
    
    // Cash received from sales payments since last closure
    $stmt1 = $db->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE i.location_id = ? AND p.method = 'cash' AND p.created_at >= ?");
    $stmt1->execute([$locationId, $lastClosure]);
    $paymentsCash = (float)$stmt1->fetchColumn();

    // Cash paid out from returns since last closure
    $stmt2 = $db->prepare("SELECT COALESCE(SUM(r.refund_paid), 0) FROM return_invoices r JOIN invoices i ON i.id = r.original_invoice_id WHERE i.location_id = ? AND r.refund_method = 'cash' AND r.created_at >= ?");
    $stmt2->execute([$locationId, $lastClosure]);
    $returnsCash = (float)$stmt2->fetchColumn();

    // Cash paid out for expenses since last closure for this location
    try {
        $stmt3 = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE location_id = ? AND payment_method = 'cash' AND created_at >= ?");
        $stmt3->execute([$locationId, $lastClosure]);
        $expensesCash = (float)$stmt3->fetchColumn();
    } catch (Throwable $e) {
        try {
            $stmt3 = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE location_id = ? AND created_at >= ?");
            $stmt3->execute([$locationId, $lastClosure]);
            $expensesCash = (float)$stmt3->fetchColumn();
        } catch (Throwable $ex) {
            $expensesCash = 0.0;
        }
    }

    return $paymentsCash - $returnsCash - $expensesCash;
}

function close_shift(int $locationId, float $actualCash, string $notes, array $user): void
{
    require_location_access($locationId);
    require_location_type($locationId, ['branch', 'warehouse'], 'إغلاق الشيفت مسموح للفروع والمخزن الرئيسي فقط.');
    $expected = expected_cash_for($locationId);
    $shiftStart = get_last_shift_closure_time($locationId);
    $db = pdo();
    $stmt = $db->prepare('INSERT INTO shift_closures (user_id, location_id, shift_date, shift_start_time, expected_cash, actual_cash, difference, notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $user['id'], $locationId, $shiftStart, $expected, $actualCash, $actualCash - $expected, $notes ?: null]);
}

function find_shift_closure(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT s.*, u.name AS user_name, l.name AS location_name FROM shift_closures s JOIN users u ON u.id = s.user_id JOIN locations l ON l.id = s.location_id WHERE s.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_shift_closure(int $id, float $actualCash, string $notes): void
{
    $shift = find_shift_closure($id);
    if (!$shift) {
        throw new RuntimeException('الشيفت غير موجود.');
    }
    require_location_access((int) $shift['location_id']);
    $difference = $actualCash - (float) $shift['expected_cash'];
    $stmt = pdo()->prepare('UPDATE shift_closures SET actual_cash = ?, difference = ?, notes = ? WHERE id = ?');
    $stmt->execute([$actualCash, $difference, $notes ?: null, $id]);
}

function delete_shift_closure(int $id): void
{
    $shift = find_shift_closure($id);
    if (!$shift) {
        throw new RuntimeException('الشيفت غير موجود.');
    }
    require_location_access((int) $shift['location_id']);
    $stmt = pdo()->prepare('DELETE FROM shift_closures WHERE id = ?');
    $stmt->execute([$id]);
}

function shift_rows(?int $locationId = null): array
{
    $sql = 'SELECT s.*, u.name AS user_name, l.name AS location_name FROM shift_closures s JOIN users u ON u.id = s.user_id JOIN locations l ON l.id = s.location_id';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE s.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY s.shift_date DESC, s.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function add_attendance(array $data, int $createdBy): void
{
    $db = pdo();
    $targetUser = find_user((int) $data['user_id']);
    if (!$targetUser) {
        throw new RuntimeException('الموظف المحدد غير موجود.');
    }
    
    // System administrator has no attendance records
    $roleCode = pdo()->query('SELECT code FROM roles WHERE id = ' . (int)$targetUser['role_id'])->fetchColumn();
    if ($roleCode === 'admin') {
        throw new RuntimeException('لا يمكن تسجيل حضور أو انصراف لمدير النظام.');
    }
    
    // Auto-resolve location: target user's location, or current user's location, or default to first location
    $locationId = (int) ($targetUser['location_id'] ?: current_user_location_id() ?: 1);
    
    // Validate access to this location
    require_location_access($locationId);
    
    // Determine custom timestamp or current time
    if (!empty($data['attendance_date']) && !empty($data['attendance_time'])) {
        $createdAt = $data['attendance_date'] . ' ' . $data['attendance_time'] . ':00';
    } else {
        $createdAt = date('Y-m-d H:i:s');
    }
    
    $latitude = !empty($data['latitude']) ? (float)$data['latitude'] : null;
    $longitude = !empty($data['longitude']) ? (float)$data['longitude'] : null;
    $source = !empty($data['source']) ? $data['source'] : 'manual';
    $notes = !empty($data['notes']) ? $data['notes'] : null;
    
    $stmt = $db->prepare('INSERT INTO attendance_records (user_id, location_id, action, latitude, longitude, source, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$targetUser['id'], $locationId, $data['action'], $latitude, $longitude, $source, $notes, $createdBy, $createdAt]);
}

function update_attendance(int $id, array $data, int $userId): void
{
    $db = pdo();
    $stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        throw new RuntimeException('سجل الحضور غير موجود.');
    }
    
    // Validate permission and location access
    require_location_access((int) $record['location_id']);
    
    if (!empty($data['attendance_date']) && !empty($data['attendance_time'])) {
        $createdAt = $data['attendance_date'] . ' ' . $data['attendance_time'] . ':00';
    } else {
        $createdAt = $record['created_at'];
    }
    
    $action = !empty($data['action']) ? $data['action'] : $record['action'];
    $notes = isset($data['notes']) ? $data['notes'] : $record['notes'];
    
    $stmt = $db->prepare('UPDATE attendance_records SET action = ?, notes = ?, created_at = ? WHERE id = ?');
    $stmt->execute([$action, $notes ?: null, $createdAt, $id]);
    
    log_audit($userId, 'update_attendance', 'attendance_records', $id, 'تعديل سجل حضور الموظف رقم ' . $record['user_id']);
}

function delete_attendance(int $id, int $userId): void
{
    $db = pdo();
    $stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        throw new RuntimeException('سجل الحضور غير موجود.');
    }
    
    // Validate permission and location access
    require_location_access((int) $record['location_id']);
    
    $stmt = $db->prepare('DELETE FROM attendance_records WHERE id = ?');
    $stmt->execute([$id]);
    
    log_audit($userId, 'delete_attendance', 'attendance_records', $id, 'حذف سجل حضور الموظف رقم ' . $record['user_id']);
}

function attendance_rows(?int $userId = null): array
{
    $sql = "SELECT a.*, u.name AS user_name, l.name AS location_name,
        (SELECT MAX(created_at) 
         FROM attendance_records 
         WHERE user_id = a.user_id 
           AND action = 'check_in' 
           AND created_at < a.created_at
           AND created_at >= DATE_SUB(a.created_at, INTERVAL 24 HOUR)
        ) AS matching_check_in
        FROM attendance_records a 
        JOIN users u ON u.id = a.user_id 
        JOIN locations l ON l.id = a.location_id";
    
    $params = [];
    if ($userId !== null) {
        $sql .= " WHERE a.user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT 100";
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function attendance_rows_filtered(array $filters = []): array
{
    $sql = "SELECT a.*, u.name AS user_name, l.name AS location_name,
        (SELECT MAX(created_at) 
         FROM attendance_records 
         WHERE user_id = a.user_id 
           AND action = 'check_in' 
           AND created_at < a.created_at
           AND created_at >= DATE_SUB(a.created_at, INTERVAL 24 HOUR)
        ) AS matching_check_in
        FROM attendance_records a 
        JOIN users u ON u.id = a.user_id 
        JOIN locations l ON l.id = a.location_id
        WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $sql .= " AND a.user_id = ?";
        $params[] = (int)$filters['user_id'];
    }
    
    if (!empty($filters['location_id'])) {
        $sql .= " AND a.location_id = ?";
        $params[] = (int)$filters['location_id'];
    }
    
    if (!empty($filters['action'])) {
        $sql .= " AND a.action = ?";
        $params[] = $filters['action'];
    }
    
    if (!empty($filters['source'])) {
        $sql .= " AND a.source = ?";
        $params[] = $filters['source'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(a.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(a.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['user_ids'])) {
        $ids = array_map('intval', (array)$filters['user_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND a.user_id IN ($placeholders)";
        $params = array_merge($params, $ids);
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function upsert_target(int $locationId, string $date, float $amount, int $userId): void
{
    require_location_access($locationId);
    require_location_type($locationId, ['branch'], 'التارجت يتم تسجيله للفروع فقط.');
    $stmt = pdo()->prepare('INSERT INTO branch_targets (location_id, target_date, target_amount, created_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount), created_by = VALUES(created_by)');
    $stmt->execute([$locationId, $date, $amount, $userId]);
}

function find_target(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT bt.*, l.name AS location_name FROM branch_targets bt JOIN locations l ON l.id = bt.location_id WHERE bt.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_target(int $id, int $locationId, string $date, float $amount, int $userId): void
{
    $target = find_target($id);
    if (!$target) {
        throw new RuntimeException('التارجت غير موجود.');
    }
    require_location_access((int) $target['location_id']);
    require_location_access($locationId);
    require_location_type($locationId, ['branch'], 'التارجت يتم تسجيله للفروع فقط.');
    $stmt = pdo()->prepare('UPDATE branch_targets SET location_id = ?, target_date = ?, target_amount = ?, created_by = ? WHERE id = ?');
    try {
        $stmt->execute([$locationId, $date, $amount, $userId, $id]);
    } catch (Throwable $e) {
        throw new RuntimeException('يوجد تارجت مسجل بالفعل لهذا الفرع في نفس اليوم.');
    }
}

function delete_target(int $id): void
{
    $target = find_target($id);
    if (!$target) {
        throw new RuntimeException('التارجت غير موجود.');
    }
    require_location_access((int) $target['location_id']);
    $stmt = pdo()->prepare('DELETE FROM branch_targets WHERE id = ?');
    $stmt->execute([$id]);
}

function target_rows(?int $locationId = null, ?string $date = null): array
{
    $sql = "SELECT bt.*, l.name AS location_name, COALESCE((SELECT SUM(total) FROM invoices i WHERE i.location_id = bt.location_id AND DATE(i.created_at) = bt.target_date), 0) AS achieved FROM branch_targets bt JOIN locations l ON l.id = bt.location_id";
    $params = [];
    $conditions = [];
    if ($locationId !== null) {
        $conditions[] = 'bt.location_id = ?';
        $params[] = $locationId;
    }
    if ($date !== null) {
        $conditions[] = 'bt.target_date = ?';
        $params[] = $date;
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY bt.target_date DESC, l.id';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function target_commission_tiers(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM target_commission_tiers';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY min_sales, sort_order, id';
    return pdo()->query($sql)->fetchAll();
}

function save_target_commission_tiers(array $data, int $userId): void
{
    $db = pdo();
    $minSales = is_array($data['min_sales'] ?? null) ? $data['min_sales'] : [];
    $maxSales = is_array($data['max_sales'] ?? null) ? $data['max_sales'] : [];
    $commissionAmounts = is_array($data['commission_amount'] ?? null) ? $data['commission_amount'] : [];
    $activeFlags = is_array($data['is_active'] ?? null) ? $data['is_active'] : [];
    $rows = [];

    foreach ($minSales as $idx => $minRaw) {
        $minRaw = trim((string) $minRaw);
        $commissionRaw = trim((string) ($commissionAmounts[$idx] ?? ''));
        if ($minRaw === '' || $commissionRaw === '' || !is_numeric($minRaw) || !is_numeric($commissionRaw)) {
            continue;
        }

        $min = (float) $minRaw;
        $commission = (float) $commissionRaw;
        $maxRaw = trim((string) ($maxSales[$idx] ?? ''));
        $max = null;
        if ($maxRaw !== '') {
            if (!is_numeric($maxRaw)) {
                continue;
            }
            $max = (float) $maxRaw;
            if ($max <= $min) {
                continue;
            }
        }

        if ($min < 0 || $commission < 0) {
            continue;
        }

        $rows[] = [
            'min_sales' => $min,
            'max_sales' => $max,
            'commission_amount' => $commission,
            'is_active' => isset($activeFlags[$idx]) ? 1 : 0,
        ];
    }

    usort($rows, fn (array $a, array $b): int => $a['min_sales'] <=> $b['min_sales']);
    $uniqueRows = [];
    $seenMins = [];
    foreach ($rows as $row) {
        $minKey = number_format($row['min_sales'], 2, '.', '');
        if (isset($seenMins[$minKey])) {
            continue;
        }
        $seenMins[$minKey] = true;
        $uniqueRows[] = $row;
    }
    $rows = $uniqueRows;

    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM target_commission_tiers');
        $stmt = $db->prepare('INSERT INTO target_commission_tiers (min_sales, max_sales, commission_amount, sort_order, is_active) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $idx => $row) {
            $stmt->execute([
                $row['min_sales'],
                $row['max_sales'],
                $row['commission_amount'],
                $idx + 1,
                $row['is_active'],
            ]);
        }
        log_audit($userId, 'update', 'target_commission_tiers', null, 'تحديث شرائح عمولات التارجت');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function commission_for_sales(float $sales): float
{
    foreach (target_commission_tiers(true) as $tier) {
        $min = (float) $tier['min_sales'];
        $max = $tier['max_sales'] !== null ? (float) $tier['max_sales'] : null;
        if ($sales >= $min && ($max === null || $sales < $max)) {
            return (float) $tier['commission_amount'];
        }
    }
    return 0.0;
}

function attendance_intervals_for_user(int $userId, string $fromDate, string $toDate, ?int $locationId = null): array
{
    $periodStart = new DateTimeImmutable(strpos($fromDate, ' ') !== false ? $fromDate : $fromDate . ' 00:00:00');
    $periodEnd = new DateTimeImmutable(strpos($toDate, ' ') !== false ? $toDate : $toDate . ' 23:59:59');
    $now = new DateTimeImmutable('now');
    if ($periodEnd > $now) {
        $periodEnd = $now;
    }

    $sql = "SELECT * FROM attendance_records
        WHERE user_id = ?
          AND (created_at BETWEEN ? AND ? OR created_at = (
              SELECT created_at FROM attendance_records
              WHERE user_id = ? AND created_at < ? AND created_at >= DATE_SUB(?, INTERVAL 24 HOUR)";
    $queryParams = [
        $userId,
        $periodStart->format('Y-m-d H:i:s'),
        $periodEnd->format('Y-m-d H:i:s'),
        $userId,
        $periodStart->format('Y-m-d H:i:s'),
        $periodStart->format('Y-m-d H:i:s'),
    ];
    if ($locationId !== null) {
        $queryParams[] = $locationId;
        $sql .= ' AND location_id = ?';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1))';
    if ($locationId !== null) {
        $sql .= ' AND location_id = ?';
        $queryParams[] = $locationId;
    }
    $sql .= ' ORDER BY created_at, id';

    $stmt = pdo()->prepare($sql);
    $stmt->execute($queryParams);
    $records = $stmt->fetchAll();
    $intervals = [];
    $open = null;

    foreach ($records as $record) {
        $createdAt = new DateTimeImmutable($record['created_at']);
        if ($record['action'] === 'check_in') {
            $open = $record;
            continue;
        }

        if ($record['action'] !== 'check_out' || $open === null) {
            continue;
        }

        $start = new DateTimeImmutable($open['created_at']);
        $end = $createdAt;
        if ($end < $periodStart || $start > $periodEnd) {
            $open = null;
            continue;
        }
        if ($start < $periodStart) {
            $start = $periodStart;
        }
        if ($end > $periodEnd) {
            $end = $periodEnd;
        }
        if ($end > $start) {
            $minutes = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
            $intervals[] = [
                'user_id' => $userId,
                'location_id' => (int) $open['location_id'],
                'check_in' => $start->format('Y-m-d H:i:s'),
                'check_out' => $end->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
            ];
        }
        $open = null;
    }

    if ($open !== null) {
        $start = new DateTimeImmutable($open['created_at']);
        if ($start < $periodStart) {
            $start = $periodStart;
        }
        if ($start < $periodEnd) {
            $minutes = (int) floor(($periodEnd->getTimestamp() - $start->getTimestamp()) / 60);
            $intervals[] = [
                'user_id' => $userId,
                'location_id' => (int) $open['location_id'],
                'check_in' => $start->format('Y-m-d H:i:s'),
                'check_out' => $periodEnd->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
            ];
        }
    }

    return $intervals;
}

function employee_sales_during_attendance(int $userId, string $fromDate, string $toDate, ?int $locationId = null): float
{
    $intervals = attendance_intervals_for_user($userId, $fromDate, $toDate, $locationId);
    if (!$intervals) {
        return 0.0;
    }

    $db = pdo();
    $stmt = $db->prepare("SELECT id, total FROM invoices WHERE status = 'completed' AND location_id = ? AND created_at BETWEEN ? AND ?");
    $seenInvoices = [];
    $total = 0.0;
    foreach ($intervals as $interval) {
        $stmt->execute([$interval['location_id'], $interval['check_in'], $interval['check_out']]);
        foreach ($stmt->fetchAll() as $invoice) {
            $invoiceId = (int) $invoice['id'];
            if (isset($seenInvoices[$invoiceId])) {
                continue;
            }
            $seenInvoices[$invoiceId] = true;
            $total += (float) $invoice['total'];
        }
    }

    return $total;
}

function get_next_attendance_action(int $userId): string
{
    $db = pdo();
    $stmt = $db->prepare("SELECT action, created_at FROM attendance_records WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $last = $stmt->fetch();
    
    if (!$last) {
        return 'check_in';
    }
    
    if ($last['action'] === 'check_in') {
        $checkInTime = new DateTimeImmutable($last['created_at']);
        $now = new DateTimeImmutable('now');
        $diffSeconds = $now->getTimestamp() - $checkInTime->getTimestamp();
        
        // 20 hours = 20 * 3600 = 72000 seconds
        if ($diffSeconds >= 0 && $diffSeconds < 72000) {
            return 'check_out';
        }
    }
    
    return 'check_in';
}

function employee_target_commission_for_period(int $userId, string $fromDate, string $toDate, ?int $locationId = null): array
{
    $db = pdo();
    
    // System administrator has no commissions
    $roleCode = pdo()->query('SELECT r.code FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = ' . (int)$userId)->fetchColumn();
    if ($roleCode === 'admin') {
        return [
            'attended_sales' => 0.0,
            'target_commission' => 0.0,
            'daily_rows' => []
        ];
    }

    if ($locationId === null) {
        $stmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $locationId = (int) $stmt->fetchColumn();
    }
    
    // Get all attendance intervals for the period
    $intervals = attendance_intervals_for_user($userId, $fromDate, $toDate, $locationId);
    
    // Group intervals by check_in date
    $intervalsByDate = [];
    foreach ($intervals as $interval) {
        $date = substr($interval['check_in'], 0, 10);
        $intervalsByDate[$date][] = $interval;
    }
    
    // Sort dates ascending
    ksort($intervalsByDate);
    
    $attendedSales = 0.0;
    $targetCommission = 0.0;
    $dailyRows = [];
    
    $invoiceStmt = $db->prepare("SELECT id, total FROM invoices WHERE status = 'completed' AND location_id = ? AND created_at BETWEEN ? AND ?");
    
    foreach ($intervalsByDate as $date => $dayIntervals) {
        $daySales = 0.0;
        $seenInvoices = [];
        
        foreach ($dayIntervals as $interval) {
            $invoiceStmt->execute([$interval['location_id'], $interval['check_in'], $interval['check_out']]);
            foreach ($invoiceStmt->fetchAll() as $invoice) {
                $invoiceId = (int) $invoice['id'];
                if (isset($seenInvoices[$invoiceId])) {
                    continue;
                }
                $seenInvoices[$invoiceId] = true;
                $daySales += (float) $invoice['total'];
            }
        }
        
        $dailyCommission = commission_for_sales($daySales);
        
        $attendedSales += $daySales;
        $targetCommission += $dailyCommission;
        $dailyRows[] = [
            'date' => $date,
            'attended_sales' => $daySales,
            'target_commission' => $dailyCommission,
        ];
    }
    
    return [
        'attended_sales' => $attendedSales,
        'target_commission' => $targetCommission,
        'daily_rows' => $dailyRows,
    ];
}

function target_employee_commission_rows(string $date, ?int $locationId = null): array
{
    $db = pdo();
    $sql = "SELECT DISTINCT u.id, u.name AS user_name, a.location_id
        FROM users u
        JOIN attendance_records a ON a.user_id = u.id
        WHERE u.is_active = 1 AND DATE(a.created_at) = ?";
    $params = [$date];
    if ($locationId !== null) {
        $sql .= ' AND a.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY u.name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $locationNames = [];
    foreach (attendance_locations() as $location) {
        $locationNames[(int) $location['id']] = $location['name'];
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $employee) {
        $rowLocationId = (int) $employee['location_id'];
        $intervals = attendance_intervals_for_user((int) $employee['id'], $date, $date, $rowLocationId);
        if (!$intervals) {
            continue;
        }
        $presentMinutes = array_sum(array_map(fn (array $interval): int => (int) $interval['minutes'], $intervals));
        $attendedSales = employee_sales_during_attendance((int) $employee['id'], $date, $date, $rowLocationId);
        $rows[] = [
            'user_id' => (int) $employee['id'],
            'user_name' => $employee['user_name'],
            'location_id' => $rowLocationId,
            'location_name' => $locationNames[$rowLocationId] ?? '',
            'attended_sales' => $attendedSales,
            'commission_amount' => commission_for_sales($attendedSales),
            'intervals_count' => count($intervals),
            'present_minutes' => $presentMinutes,
        ];
    }

    return $rows;
}

function target_branch_summary_rows(string $date, ?int $locationId = null): array
{
    $locations = sale_locations();
    if ($locationId !== null) {
        $locations = array_values(array_filter($locations, fn (array $location): bool => (int) $location['id'] === $locationId));
    }

    $db = pdo();
    $salesStmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status = 'completed' AND location_id = ? AND DATE(created_at) = ?");
    $rows = [];
    foreach ($locations as $location) {
        $locId = (int) $location['id'];
        $salesStmt->execute([$locId, $date]);
        $employeeRows = target_employee_commission_rows($date, $locId);
        $rows[] = [
            'location_id' => $locId,
            'location_name' => $location['name'],
            'daily_sales' => (float) $salesStmt->fetchColumn(),
            'present_employees' => count($employeeRows),
            'total_commissions' => array_sum(array_map(fn (array $row): float => (float) $row['commission_amount'], $employeeRows)),
        ];
    }

    return $rows;
}

function payroll_rows_for_month(string $month): array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m', $month) ?: new DateTimeImmutable('first day of this month');
    $fromDate = $start->format('Y-m-01');
    $toDate = $start->modify('last day of this month')->format('Y-m-d');
    $employees = pdo()->query('SELECT u.*, r.name AS role_name, r.code AS role_code, l.name AS location_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN locations l ON l.id = u.location_id WHERE u.is_active = 1 AND r.code != \'admin\' ORDER BY u.name')->fetchAll();
    $db = pdo();
    $daysStmt = $db->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM attendance_records WHERE user_id = ? AND action = 'check_in' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $delaysStmt = $db->prepare("SELECT COUNT(*) FROM attendance_records WHERE user_id = ? AND action = 'check_in' AND DATE_FORMAT(created_at, '%Y-%m') = ? AND TIME(created_at) > '09:00:00'");
    $adjustmentsByUser = [];
    foreach (payroll_adjustments_for_month($month) as $adjustment) {
        $adjustmentsByUser[(int) $adjustment['user_id']][] = $adjustment;
    }
    $overridesByUser = payroll_overrides_for_month($month);
    $rows = [];

    foreach ($employees as $employee) {
        $employeeId = (int) $employee['id'];
        $daysStmt->execute([(int) $employee['id'], $month]);
        $delaysStmt->execute([(int) $employee['id'], $month]);
        $targetCommissionSummary = employee_target_commission_for_period($employeeId, $fromDate, $toDate);
        $attendedSales = (float) $targetCommissionSummary['attended_sales'];
        $commissionCalculated = (float) $targetCommissionSummary['target_commission'];
        $adjustments = $adjustmentsByUser[$employeeId] ?? [];
        $additionsCalculated = 0.0;
        $deductionsCalculated = 0.0;
        foreach ($adjustments as $adjustment) {
            if ($adjustment['type'] === 'bonus') {
                $additionsCalculated += (float) $adjustment['amount'];
            } elseif ($adjustment['type'] === 'deduction') {
                $deductionsCalculated += (float) $adjustment['amount'];
            }
        }
        $override = $overridesByUser[$employeeId] ?? null;
        $commissionFinal = $override !== null && $override['commission_total'] !== null ? (float) $override['commission_total'] : $commissionCalculated;
        $additionsFinal = $override !== null && $override['additions_total'] !== null ? (float) $override['additions_total'] : $additionsCalculated;
        $deductionsFinal = $override !== null && $override['deductions_total'] !== null ? (float) $override['deductions_total'] : $deductionsCalculated;
        $rows[] = [
            'user' => $employee,
            'days_present' => (int) $daysStmt->fetchColumn(),
            'delays' => (int) $delaysStmt->fetchColumn(),
            'attended_sales' => $attendedSales,
            'target_commission' => $commissionFinal,
            'commission_calculated' => $commissionCalculated,
            'commission_final' => $commissionFinal,
            'additions_calculated' => $additionsCalculated,
            'additions_final' => $additionsFinal,
            'deductions_calculated' => $deductionsCalculated,
            'deductions_final' => $deductionsFinal,
            'adjustments' => $adjustments,
            'override' => $override,
            'target_commission_daily_rows' => $targetCommissionSummary['daily_rows'],
            'daily_rows' => $targetCommissionSummary['daily_rows'],
            'total_payout' => (float) $employee['basic_salary'] + $commissionFinal + $additionsFinal - $deductionsFinal,
        ];
    }

    return $rows;
}

function payroll_adjustments_for_month(string $month, ?int $userId = null): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new RuntimeException('شهر الرواتب غير صحيح.');
    }

    $sql = "SELECT pa.*, u.name AS user_name, creator.name AS created_by_name
        FROM payroll_adjustments pa
        JOIN users u ON u.id = pa.user_id
        JOIN users creator ON creator.id = pa.created_by
        WHERE pa.adjustment_month = ?";
    $params = [$month];
    if ($userId !== null) {
        $sql .= ' AND pa.user_id = ?';
        $params[] = $userId;
    }
    $sql .= " ORDER BY u.name, pa.created_at DESC, pa.id DESC";
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function add_payroll_adjustment(array $data, int $createdBy): void
{
    $userId = (int) ($data['user_id'] ?? 0);
    $month = trim((string) ($data['adjustment_month'] ?? $data['month'] ?? ''));
    $type = (string) ($data['type'] ?? '');
    $amount = (float) ($data['amount'] ?? 0);
    $reason = trim((string) ($data['reason'] ?? ''));

    if ($userId <= 0 || !find_user($userId)) {
        throw new RuntimeException('اختر موظفاً صحيحاً.');
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new RuntimeException('شهر الحافز أو الخصم غير صحيح.');
    }
    if (!in_array($type, ['bonus', 'deduction'], true)) {
        throw new RuntimeException('نوع الحركة غير صحيح.');
    }
    if ($amount <= 0) {
        throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر.');
    }

    $stmt = pdo()->prepare('INSERT INTO payroll_adjustments (user_id, adjustment_month, type, amount, reason, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $month, $type, $amount, $reason !== '' ? $reason : null, $createdBy]);
    $id = (int) pdo()->lastInsertId();
    log_audit($createdBy, 'create', 'payroll_adjustment', $id, ($type === 'bonus' ? 'إضافة حافز' : 'إضافة خصم') . ' للموظف #' . $userId . ' عن شهر ' . $month . ' بقيمة ' . number_format($amount, 2, '.', ''));
}

function delete_payroll_adjustment(int $id, int $userId): void
{
    $stmt = pdo()->prepare('SELECT * FROM payroll_adjustments WHERE id = ?');
    $stmt->execute([$id]);
    $adjustment = $stmt->fetch();
    if (!$adjustment) {
        throw new RuntimeException('الحافز أو الخصم غير موجود.');
    }

    pdo()->prepare('DELETE FROM payroll_adjustments WHERE id = ?')->execute([$id]);
    log_audit($userId, 'delete', 'payroll_adjustment', $id, 'حذف ' . ($adjustment['type'] === 'bonus' ? 'حافز' : 'خصم') . ' للموظف #' . $adjustment['user_id'] . ' عن شهر ' . $adjustment['adjustment_month']);
}

function payroll_overrides_for_month(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new RuntimeException('شهر الرواتب غير صحيح.');
    }

    $stmt = pdo()->prepare("SELECT po.*, updater.name AS updated_by_name
        FROM payroll_overrides po
        JOIN users updater ON updater.id = po.updated_by
        WHERE po.payroll_month = ?
        ORDER BY po.user_id");
    $stmt->execute([$month]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) $row['user_id']] = $row;
    }
    return $rows;
}

function payroll_manual_overrides_for_month(string $month): array
{
    return payroll_overrides_for_month($month);
}

function save_payroll_override(array $data, int $updatedBy): void
{
    $userId = (int) ($data['user_id'] ?? 0);
    $month = trim((string) ($data['payroll_month'] ?? $data['month'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));

    if ($userId <= 0 || !find_user($userId)) {
        throw new RuntimeException('اختر موظفاً صحيحاً.');
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new RuntimeException('شهر الرواتب غير صحيح.');
    }

    $commissionTotal = payroll_nullable_amount($data['commission_total'] ?? null, 'إجمالي العمولة');
    $additionsTotal = payroll_nullable_amount($data['additions_total'] ?? null, 'إجمالي الإضافات');
    $deductionsTotal = payroll_nullable_amount($data['deductions_total'] ?? null, 'إجمالي الخصومات');

    $stmt = pdo()->prepare("INSERT INTO payroll_overrides (user_id, payroll_month, commission_total, additions_total, deductions_total, notes, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE commission_total = VALUES(commission_total), additions_total = VALUES(additions_total), deductions_total = VALUES(deductions_total), notes = VALUES(notes), updated_by = VALUES(updated_by)");
    $stmt->execute([$userId, $month, $commissionTotal, $additionsTotal, $deductionsTotal, $notes !== '' ? $notes : null, $updatedBy]);
    log_audit($updatedBy, 'update', 'payroll_override', $userId, 'تعديل إجماليات راتب الموظف #' . $userId . ' عن شهر ' . $month);
}

function payroll_nullable_amount(mixed $value, string $label): ?float
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return null;
    }
    $amount = (float) $value;
    if ($amount < 0) {
        throw new RuntimeException($label . ' لا يمكن أن يكون سالباً.');
    }
    return $amount;
}

function pay_salary(array $data, int $paidBy): void
{
    $db = pdo();
    $userId = (int) $data['user_id'];
    $month = trim((string) $data['payroll_month']);

    // Check if already paid
    $chk = $db->prepare('SELECT id FROM salary_payments WHERE user_id = ? AND payroll_month = ?');
    $chk->execute([$userId, $month]);
    if ($chk->fetch()) {
        throw new RuntimeException('تم صرف راتب هذا الشهر بالفعل لهذا الموظف.');
    }

    // Fetch user record directly
    $uStmt = $db->prepare('SELECT id, name, location_id FROM users WHERE id = ?');
    $uStmt->execute([$userId]);
    $targetUser = $uStmt->fetch();
    if (!$targetUser) {
        throw new RuntimeException('الموظف غير موجود.');
    }

    $basicSalary = (float) $data['basic_salary'];
    $commission  = (float) $data['commission'];
    $additions   = (float) $data['additions'];
    $deductions  = (float) $data['deductions'];
    $netPayout   = (float) $data['net_payout'];
    $method      = in_array($data['payment_method'] ?? '', ['cash','instapay','vodafone_cash','bank_transfer'], true)
                   ? $data['payment_method'] : 'cash';

    // Determine payment source (branch treasury vs manager treasury)
    if (($data['payment_source'] ?? '') === 'manager_treasury') {
        $locationId = null;
    } else {
        // Use employee's branch; fallback: the admin's branch
        $locationId = $targetUser['location_id'] ?: null;
        if ($locationId === null) {
            $locStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
            $locStmt->execute([$paidBy]);
            $locationId = $locStmt->fetchColumn() ?: null;
        }
    }

    $ins = $db->prepare('INSERT INTO salary_payments (user_id, payroll_month, basic_salary, commission, additions, deductions, net_payout, payment_method, location_id, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([$userId, $month, $basicSalary, $commission, $additions, $deductions, $netPayout, $method, $locationId, $paidBy]);

    log_audit($paidBy, 'pay_salary', 'users', $userId, sprintf(
        'صرف راتب الموظف %s لشهر %s بقيمة %.2f - طريقة الدفع: %s',
        $targetUser['name'], $month, $netPayout, $method
    ));
}

function salary_payments_list(array $filters = []): array
{
    $db = pdo();
    $sql = "SELECT s.*, u.name AS user_name, admin.name AS admin_name, l.name AS location_name
            FROM salary_payments s
            JOIN users u ON u.id = s.user_id
            JOIN users admin ON admin.id = s.paid_by
            LEFT JOIN locations l ON l.id = s.location_id";
            
    $where = [];
    $params = [];
    
    if (!empty($filters['month'])) {
        $where[] = 's.payroll_month = ?';
        $params[] = $filters['month'];
    }
    if (!empty($filters['user_id'])) {
        $where[] = 's.user_id = ?';
        $params[] = (int) $filters['user_id'];
    }
    if (!empty($filters['location_id'])) {
        $where[] = 's.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }
    
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $sql .= ' ORDER BY s.paid_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function payroll_paid_statuses_for_month(string $month): array
{
    $db = pdo();
    $stmt = $db->prepare('SELECT user_id, id, paid_at, payment_method, net_payout FROM salary_payments WHERE payroll_month = ?');
    $stmt->execute([$month]);
    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $results[(int)$row['user_id']] = $row;
    }
    return $results;
}

function auto_create_daily_targets(int $userId): void
{
    $db = pdo();
    $today = date('Y-m-d');
    
    // Get all active branches
    $branches = pdo()->query("SELECT id FROM locations WHERE type = 'branch' AND is_active = 1")->fetchAll();
    
    foreach ($branches as $branch) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM branch_targets WHERE location_id = ? AND target_date = ?');
        $stmt->execute([(int) $branch['id'], $today]);
        $exists = (int) $stmt->fetchColumn() > 0;
        
        if (!$exists) {
            // Create target with 0 amount (will be updated by manager)
            $stmt = $db->prepare('INSERT INTO branch_targets (location_id, target_date, target_amount, created_by) VALUES (?, ?, 0, ?)');
            $stmt->execute([(int) $branch['id'], $today, $userId]);
        }
    }
}

function expense_categories(): array
{
    return pdo()->query('SELECT * FROM expense_categories ORDER BY name')->fetchAll();
}

function add_expense(array $data, int $userId): void
{
    $locationId = $data['location_id'] !== '' ? (int) $data['location_id'] : null;
    if ($locationId !== null) {
        require_location_access($locationId);
    }
    $paymentMethod = !empty($data['payment_method']) ? $data['payment_method'] : 'cash';
    $stmt = pdo()->prepare('INSERT INTO expenses (category_id, location_id, amount, expense_date, notes, created_by, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $data['category_id'], $locationId, (float) $data['amount'], $data['expense_date'], $data['notes'] ?: null, $userId, $paymentMethod]);
}

function get_expense_by_id(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    $expense = $stmt->fetch();
    return $expense ?: null;
}

function edit_expense(int $id, array $data, int $userId): void
{
    $expense = get_expense_by_id($id);
    if (!$expense) throw new RuntimeException('المصروف غير موجود');
    
    $locationId = $data['location_id'] !== '' ? (int) $data['location_id'] : null;
    if ($locationId !== null) {
        require_location_access($locationId);
    }
    $paymentMethod = !empty($data['payment_method']) ? $data['payment_method'] : 'cash';
    $stmt = pdo()->prepare('UPDATE expenses SET category_id = ?, location_id = ?, amount = ?, expense_date = ?, notes = ?, payment_method = ? WHERE id = ?');
    $stmt->execute([(int) $data['category_id'], $locationId, (float) $data['amount'], $data['expense_date'], $data['notes'] ?: null, $paymentMethod, $id]);
}

function delete_expense(int $id, int $userId): void
{
    $expense = get_expense_by_id($id);
    if (!$expense) throw new RuntimeException('المصروف غير موجود');
    
    if ($expense['location_id'] !== null) {
        require_location_access((int) $expense['location_id']);
    }
    
    $stmt = pdo()->prepare('DELETE FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
}

function expense_rows(?int $locationId = null): array
{
    $sql = 'SELECT e.*, c.name AS category_name, l.name AS location_name, u.name AS user_name 
            FROM expenses e 
            JOIN expense_categories c ON c.id = e.category_id 
            LEFT JOIN locations l ON l.id = e.location_id 
            JOIN users u ON u.id = e.created_by';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE e.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY e.expense_date DESC, e.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function add_supplier(array $data, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO suppliers (name, phone, product_type, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['phone'] ?: null, $data['product_type'] ?: null, $data['notes'] ?: null]);
        $supplierId = (int) $db->lastInsertId();
        if ((float) ($data['invoice_total'] ?? 0) > 0) {
            $total = (float) $data['invoice_total'];
            $paid = (float) ($data['invoice_paid'] ?? 0);
            $stmt = $db->prepare('INSERT INTO supplier_invoices (supplier_id, invoice_number, total, paid, due, invoice_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$supplierId, $data['invoice_number'] ?: null, $total, $paid, max(0, $total - $paid), $data['invoice_date'] ?: date('Y-m-d'), $data['notes'] ?: null, $userId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function supplier_rows(): array
{
    return pdo()->query('SELECT s.*, COALESCE(SUM(si.total), 0) AS total_invoices, COALESCE(SUM(si.due), 0) AS total_due FROM suppliers s LEFT JOIN supplier_invoices si ON si.supplier_id = s.id GROUP BY s.id ORDER BY s.created_at DESC')->fetchAll();
}

function report_stats(array $filters = []): array
{
    $db = pdo();
    $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : null;
    $endDate = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : null;
    $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;

    // 1. Sales by Location
    $sqlLocation = "SELECT l.name, COALESCE(SUM(i.total), 0) AS total 
                    FROM locations l 
                    LEFT JOIN invoices i ON i.location_id = l.id";
    $locParams = [];
    $locJoinWhere = [];
    if ($startDate) {
        $locJoinWhere[] = "i.created_at >= ?";
        $locParams[] = $startDate;
    }
    if ($endDate) {
        $locJoinWhere[] = "i.created_at <= ?";
        $locParams[] = $endDate;
    }
    if ($locationId) {
        $locJoinWhere[] = "i.location_id = ?";
        $locParams[] = $locationId;
    }
    if ($locJoinWhere) {
        $sqlLocation .= " AND " . implode(" AND ", $locJoinWhere);
    }
    if ($locationId) {
        $sqlLocation .= " WHERE l.id = ?";
        $locParams[] = $locationId;
    }
    $sqlLocation .= " GROUP BY l.id ORDER BY l.id";
    $stmtLoc = $db->prepare($sqlLocation);
    $stmtLoc->execute($locParams);
    $salesByLocation = $stmtLoc->fetchAll();

    // 2. Sales by Payment
    $sqlPayment = "SELECT p.method, COALESCE(SUM(p.amount), 0) AS total 
                   FROM payments p 
                   LEFT JOIN invoices i ON i.id = p.invoice_id";
    $payWhere = " WHERE 1=1";
    $payParams = [];
    if ($startDate) {
        $payWhere .= " AND p.created_at >= ?";
        $payParams[] = $startDate;
    }
    if ($endDate) {
        $payWhere .= " AND p.created_at <= ?";
        $payParams[] = $endDate;
    }
    if ($locationId) {
        $payWhere .= " AND i.location_id = ?";
        $payParams[] = $locationId;
    }
    $sqlPayment .= $payWhere . " GROUP BY p.method";
    $stmtPay = $db->prepare($sqlPayment);
    $stmtPay->execute($payParams);
    $salesByPayment = $stmtPay->fetchAll();

    // 3. Sales by User
    $sqlUser = "SELECT u.name, COALESCE(SUM(i.total), 0) AS total, COUNT(i.id) AS invoices_count 
                FROM users u 
                LEFT JOIN invoices i ON i.user_id = u.id";
    $userParams = [];
    $userJoinWhere = [];
    if ($startDate) {
        $userJoinWhere[] = "i.created_at >= ?";
        $userParams[] = $startDate;
    }
    if ($endDate) {
        $userJoinWhere[] = "i.created_at <= ?";
        $userParams[] = $endDate;
    }
    if ($locationId) {
        $userJoinWhere[] = "i.location_id = ?";
        $userParams[] = $locationId;
    }
    if ($userJoinWhere) {
        $sqlUser .= " AND " . implode(" AND ", $userJoinWhere);
    }
    if ($locationId) {
        $sqlUser .= " WHERE u.location_id = ?";
        $userParams[] = $locationId;
    }
    $sqlUser .= " GROUP BY u.id ORDER BY total DESC";
    $stmtUser = $db->prepare($sqlUser);
    $stmtUser->execute($userParams);
    $salesByUser = $stmtUser->fetchAll();

    // 4. Top Products
    $sqlTop = "SELECT l.description, SUM(l.quantity) AS qty_sold, SUM(l.line_total) AS total 
               FROM invoice_lines l 
               JOIN invoices i ON i.id = l.invoice_id";
    $topWhere = " WHERE 1=1";
    $topParams = [];
    if ($startDate) {
        $topWhere .= " AND i.created_at >= ?";
        $topParams[] = $startDate;
    }
    if ($endDate) {
        $topWhere .= " AND i.created_at <= ?";
        $topParams[] = $endDate;
    }
    if ($locationId) {
        $topWhere .= " AND i.location_id = ?";
        $topParams[] = $locationId;
    }
    $sqlTop .= $topWhere . " GROUP BY l.description ORDER BY total DESC LIMIT 5";
    $stmtTop = $db->prepare($sqlTop);
    $stmtTop->execute($topParams);
    $topProducts = $stmtTop->fetchAll();

    // 5. Perfume Usage
    $sqlUsage = "SELECT p.name, SUM(c.quantity) AS grams 
                 FROM invoice_line_components c 
                 JOIN products p ON p.id = c.component_product_id 
                 JOIN invoice_lines l ON l.id = c.invoice_line_id 
                 JOIN invoices i ON i.id = l.invoice_id";
    $useWhere = " WHERE p.type = 'perfume_gram'";
    $useParams = [];
    if ($startDate) {
        $useWhere .= " AND i.created_at >= ?";
        $useParams[] = $startDate;
    }
    if ($endDate) {
        $useWhere .= " AND i.created_at <= ?";
        $useParams[] = $endDate;
    }
    if ($locationId) {
        $useWhere .= " AND i.location_id = ?";
        $useParams[] = $locationId;
    }
    $sqlUsage .= $useWhere . " GROUP BY p.id ORDER BY grams DESC";
    $stmtUsage = $db->prepare($sqlUsage);
    $stmtUsage->execute($useParams);
    $perfumeUsage = $stmtUsage->fetchAll();

    // 6. New Customers
    $sqlCust = "SELECT name AS 'اسم العميل', phone AS 'الهاتف', source AS 'المصدر', DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS 'تاريخ التسجيل' 
                FROM customers";
    $custWhere = " WHERE is_active = 1";
    $custParams = [];
    if ($startDate) {
        $custWhere .= " AND created_at >= ?";
        $custParams[] = $startDate;
    }
    if ($endDate) {
        $custWhere .= " AND created_at <= ?";
        $custParams[] = $endDate;
    }
    $sqlCust .= $custWhere . " ORDER BY created_at DESC LIMIT 10";
    $stmtCust = $db->prepare($sqlCust);
    $stmtCust->execute($custParams);
    $newCustomers = $stmtCust->fetchAll();

    return [
        'sales_by_location' => $salesByLocation,
        'sales_by_payment' => $salesByPayment,
        'sales_by_user' => $salesByUser,
        'top_products' => $topProducts,
        'perfume_usage' => $perfumeUsage,
        'new_customers' => $newCustomers,
    ];
}

function report_rows(string $key, array $filters = []): array
{
    $reports = report_stats($filters);
    return $reports[$key] ?? [];
}

function output_csv(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

function recent_invoices(int $limit = 50, ?int $locationId = null): array
{
    $sql = 'SELECT i.*, l.name AS location_name, u.name AS user_name, c.name AS customer_name 
            FROM invoices i 
            JOIN locations l ON l.id = i.location_id 
            JOIN users u ON u.id = i.user_id 
            LEFT JOIN customers c ON c.id = i.customer_id';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE i.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY i.created_at DESC, i.id DESC LIMIT ' . (int) $limit;
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function search_invoices(array $filters): array
{
    $db = pdo();
    $sql = 'SELECT i.*, l.name AS location_name, u.name AS user_name, c.name AS customer_name 
            FROM invoices i 
            JOIN locations l ON l.id = i.location_id 
            JOIN users u ON u.id = i.user_id 
            LEFT JOIN customers c ON c.id = i.customer_id ';

    // payment_method requires a subquery join on payments table
    if (!empty($filters['payment_method'])) {
        $sql .= ' INNER JOIN payments pm ON pm.invoice_id = i.id AND pm.method = ? ';
    }

    $sql .= ' WHERE 1=1';
    $params = [];

    // inject payment_method param early (it was added to FROM clause)
    if (!empty($filters['payment_method'])) {
        array_unshift($params, $filters['payment_method']);
    }

    if (!empty($filters['location_id'])) {
        $sql .= ' AND i.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }

    if (!empty($filters['user_id'])) {
        $sql .= ' AND i.user_id = ?';
        $params[] = (int) $filters['user_id'];
    }

    if (!empty($filters['customer_id'])) {
        $sql .= ' AND i.customer_id = ?';
        $params[] = (int) $filters['customer_id'];
    }

    if (!empty($filters['start_date'])) {
        $sql .= ' AND i.created_at >= ?';
        $params[] = $filters['start_date'] . ' 00:00:00';
    }

    if (!empty($filters['end_date'])) {
        $sql .= ' AND i.created_at <= ?';
        $params[] = $filters['end_date'] . ' 23:59:59';
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (i.invoice_number LIKE ? OR c.name LIKE ? OR l.name LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $userLocationId = current_user_location_id();
    if ($userLocationId !== null) {
        $sql .= ' AND i.location_id = ?';
        $params[] = $userLocationId;
    }

    $sql .= ' GROUP BY i.id ORDER BY i.created_at DESC, i.id DESC LIMIT 200';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_invoice(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT i.*, l.name AS location_name, u.name AS user_name, c.name AS customer_name, c.phone AS customer_phone FROM invoices i JOIN locations l ON l.id = i.location_id JOIN users u ON u.id = i.user_id LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function invoice_lines_rows(int $invoiceId): array
{
    $stmt = pdo()->prepare('SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY id');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

function invoice_components_rows(int $lineId): array
{
    $stmt = pdo()->prepare("SELECT c.*, p.name AS product_name, p.unit, b.size_ml 
        FROM invoice_line_components c 
        JOIN products p ON p.id = c.component_product_id 
        LEFT JOIN product_bottle_details b ON b.product_id = p.id 
        WHERE c.invoice_line_id = ? ORDER BY c.id");
    $stmt->execute([$lineId]);
    return $stmt->fetchAll();
}

function invoice_payments_rows(int $invoiceId): array
{
    $stmt = pdo()->prepare('SELECT p.*, u.name AS user_name FROM payments p JOIN users u ON u.id = p.created_by WHERE p.invoice_id = ? ORDER BY p.created_at');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

function dashboard_shift_stats(?int $locationId = null): array
{
    $db = pdo();
    $totalSales = 0.0;
    $totalInvoices = 0;
    $totalCustomers = 0;
    
    if ($locationId !== null) {
        $shiftStart = get_last_shift_closure_time($locationId);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE location_id = ? AND created_at >= ?");
        $stmt->execute([$locationId, $shiftStart]);
        $totalSales = (float)$stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE location_id = ? AND created_at >= ?");
        $stmt->execute([$locationId, $shiftStart]);
        $totalInvoices = (int)$stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE location_id = ? AND created_at >= ? AND is_active = 1");
        $stmt->execute([$locationId, $shiftStart]);
        $totalCustomers = (int)$stmt->fetchColumn();
        
    } else {
        $branches = sale_locations();
        foreach ($branches as $branch) {
            $branchId = (int)$branch['id'];
            $shiftStart = get_last_shift_closure_time($branchId);
            
            if ($shiftStart === '1970-01-01 00:00:00') {
                continue;
            }
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE location_id = ? AND created_at >= ?");
            $stmt->execute([$branchId, $shiftStart]);
            $totalSales += (float)$stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE location_id = ? AND created_at >= ?");
            $stmt->execute([$branchId, $shiftStart]);
            $totalInvoices += (int)$stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE location_id = ? AND created_at >= ? AND is_active = 1");
            $stmt->execute([$branchId, $shiftStart]);
            $totalCustomers += (int)$stmt->fetchColumn();
        }
    }
    
    return [
        'shift_sales' => $totalSales,
        'shift_invoices' => $totalInvoices,
        'shift_customers' => $totalCustomers,
    ];
}

function dashboard_shift_location_sales(?int $locationId = null): array
{
    $db = pdo();
    $branches = sale_locations(); // only branch type
    $result = [];

    foreach ($branches as $branch) {
        $branchId = (int)$branch['id'];
        
        // If user is scoped to a location, skip other branches
        if ($locationId !== null && $branchId !== $locationId) {
            continue;
        }
        
        $shiftStart = get_last_shift_closure_time($branchId);
        if ($shiftStart === '1970-01-01 00:00:00') {
            $result[] = [
                'name' => $branch['name'],
                'type' => $branch['type'],
                'shift_sales' => 0,
                'actual_shift_start' => null,
                'employees_count' => 0,
            ];
            continue;
        }
        
        // Determine actual shift start: first check-in, or first invoice, or last closure
        $firstCheckin = shift_first_checkin($branchId, $shiftStart);
        $firstInvoice = shift_first_invoice($branchId, $shiftStart);
        
        if ($firstCheckin) {
            $actualShiftStart = $firstCheckin['created_at'];
        } elseif ($firstInvoice) {
            $actualShiftStart = $firstInvoice['created_at'];
        } else {
            $actualShiftStart = $shiftStart;
        }
        
        // Get sales for this shift
        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE location_id = ? AND created_at >= ?");
        $stmt->execute([$branchId, $shiftStart]);
        $sales = (float)$stmt->fetchColumn();
        
        // Count unique employees who checked in during this shift and are still checked in (no check-out after their last check-in)
        $empStmt = $db->prepare("SELECT COUNT(DISTINCT a.user_id) FROM attendance_records a 
            WHERE a.location_id = ? AND a.action = 'check_in' AND a.created_at >= ?
            AND a.user_id NOT IN (
                SELECT a2.user_id FROM attendance_records a2 
                WHERE a2.location_id = ? AND a2.action = 'check_out' AND a2.created_at > a.created_at
            )");
        $empStmt->execute([$branchId, $shiftStart, $branchId]);
        $employeesCount = (int)$empStmt->fetchColumn();
        
        $result[] = [
            'name' => $branch['name'],
            'type' => $branch['type'],
            'shift_sales' => $sales,
            'actual_shift_start' => $actualShiftStart,
            'employees_count' => $employeesCount,
        ];
    }

    return $result;
}

function dashboard_stats(?int $locationId = null): array
{
    $db = pdo();
    $params = [];
    $customerLocationJoin = ' LEFT JOIN (
        SELECT customer_id, MIN(location_id) AS location_id
        FROM invoices
        WHERE customer_id IS NOT NULL AND location_id IS NOT NULL
        GROUP BY customer_id
    ) derived ON derived.customer_id = c.id';
    $effectiveCustomerLocation = 'COALESCE(c.location_id, derived.location_id)';
    
    $todaySalesQuery = 'SELECT COALESCE(SUM(total), 0) FROM invoices WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY';
    $todayInvoicesQuery = 'SELECT COUNT(*) FROM invoices WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY';
    $todayCustomersQuery = 'SELECT COUNT(*) FROM customers c' . $customerLocationJoin . ' WHERE c.is_active = 1 AND c.created_at >= CURDATE() AND c.created_at < CURDATE() + INTERVAL 1 DAY';
    $totalCustomersQuery = 'SELECT COUNT(*) FROM customers c' . $customerLocationJoin . ' WHERE c.is_active = 1';
    $openDebtsQuery = "SELECT COALESCE(SUM(remaining_amount), 0) FROM customer_debts WHERE status = 'open'";
    $monthExpensesQuery = "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    $lowStockCountQuery = 'SELECT COUNT(*) FROM inventory_balances ib JOIN products p ON p.id = ib.product_id WHERE p.min_stock > 0 AND ib.quantity <= p.min_stock';

    if ($locationId !== null) {
        $todaySalesQuery .= ' AND location_id = ?';
        $todayInvoicesQuery .= ' AND location_id = ?';
        $todayCustomersQuery .= ' AND ' . $effectiveCustomerLocation . ' = ?';
        $totalCustomersQuery .= ' AND ' . $effectiveCustomerLocation . ' = ?';
        $openDebtsQuery = "SELECT COALESCE(SUM(d.remaining_amount), 0) FROM customer_debts d LEFT JOIN invoices i ON i.id = d.invoice_id WHERE d.status = 'open' AND COALESCE(d.location_id, i.location_id) = ?";
        $monthExpensesQuery .= ' AND location_id = ?';
        $lowStockCountQuery .= ' AND ib.location_id = ?';
        
        $params = [$locationId];
    }

    $todaySalesStmt = $db->prepare($todaySalesQuery);
    $todaySalesStmt->execute($params);
    $todaySales = (float) $todaySalesStmt->fetchColumn();

    $todayInvoicesStmt = $db->prepare($todayInvoicesQuery);
    $todayInvoicesStmt->execute($params);
    $todayInvoices = (int) $todayInvoicesStmt->fetchColumn();

    $todayCustomersStmt = $db->prepare($todayCustomersQuery);
    $todayCustomersStmt->execute($params);
    $todayCustomers = (int) $todayCustomersStmt->fetchColumn();

    $totalCustomersStmt = $db->prepare($totalCustomersQuery);
    $totalCustomersStmt->execute($params);
    $totalCustomers = (int) $totalCustomersStmt->fetchColumn();

    $openDebtsStmt = $db->prepare($openDebtsQuery);
    $openDebtsStmt->execute($params);
    $openDebts = (float) $openDebtsStmt->fetchColumn();

    $monthExpensesStmt = $db->prepare($monthExpensesQuery);
    $monthExpensesStmt->execute($params);
    $monthExpenses = (float) $monthExpensesStmt->fetchColumn();

    $lowStockCountStmt = $db->prepare($lowStockCountQuery);
    $lowStockCountStmt->execute($params);
    $lowStockCount = (int) $lowStockCountStmt->fetchColumn();

    return [
        'today_sales' => $todaySales,
        'today_invoices' => $todayInvoices,
        'today_customers' => $todayCustomers,
        'customers' => $totalCustomers,
        'open_debts' => $openDebts,
        'month_expenses' => $monthExpenses,
        'low_stock_count' => $lowStockCount,
    ];
}

function dashboard_location_sales(?int $locationId = null): array
{
    $sql = 'SELECT l.name, l.type, COALESCE(SUM(CASE WHEN i.created_at >= CURDATE() AND i.created_at < CURDATE() + INTERVAL 1 DAY THEN i.total ELSE 0 END), 0) AS today_sales FROM locations l LEFT JOIN invoices i ON i.location_id = l.id';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE l.id = ?';
        $params[] = $locationId;
    }
    $sql .= ' GROUP BY l.id ORDER BY l.id';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function low_stock_rows(?int $locationId = null): array
{
    $sql = 'SELECT ib.*, l.name AS location_name, p.name AS product_name, p.unit, p.min_stock FROM inventory_balances ib JOIN products p ON p.id = ib.product_id JOIN locations l ON l.id = ib.location_id WHERE p.min_stock > 0 AND ib.quantity <= p.min_stock';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' AND ib.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY ib.quantity ASC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function debts_rows(?int $locationId = null, array $filters = []): array
{
    $sql = "SELECT d.*, c.name AS customer_name, c.phone, 
                   COALESCE(i.invoice_number, CONCAT('دين مباشر', IF(d.notes IS NOT NULL AND d.notes != '', CONCAT(' (', d.notes, ')'), ''))) AS invoice_number, 
                   COALESCE(i.created_at, d.created_at) AS invoice_created_at, 
                   COALESCE(l.name, 'دين عام') AS location_name
            FROM customer_debts d
            JOIN customers c ON c.id = d.customer_id
            LEFT JOIN invoices i ON i.id = d.invoice_id
            LEFT JOIN locations l ON l.id = COALESCE(d.location_id, i.location_id)
            WHERE d.status = 'open'";
    $params = [];

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $sql .= ' AND COALESCE(i.created_at, d.created_at) >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $sql .= ' AND COALESCE(i.created_at, d.created_at) <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    if ($locationId !== null) {
        $sql .= ' AND COALESCE(d.location_id, i.location_id) = ?';
        $params[] = $locationId;
    } else {
        $filterLocationId = (int) ($filters['location_id'] ?? 0);
        if ($filterLocationId > 0) {
            $sql .= ' AND COALESCE(d.location_id, i.location_id) = ?';
            $params[] = $filterLocationId;
        }
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR i.invoice_number LIKE ? OR d.notes LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $customerId = (int) ($filters['customer_id'] ?? 0);
    if ($customerId > 0) {
        $sql .= ' AND d.customer_id = ?';
        $params[] = $customerId;
    }

    $sql .= ' ORDER BY d.created_at DESC, d.id DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function notification_rows(): array
{
    $db = pdo();
    $rows = [];

    foreach (low_stock_rows() as $row) {
        $rows[] = [
            'type' => 'مخزون',
            'title' => 'صنف وصل للحد الأدنى',
            'details' => $row['product_name'] . ' في ' . $row['location_name'] . ' الرصيد ' . qty($row['quantity']) . ' والحد ' . qty($row['min_stock']),
            'severity' => 'warning',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    $stmt = $db->query("SELECT d.*, c.name AS customer_name, DATEDIFF(CURDATE(), DATE(d.created_at)) AS age_days FROM customer_debts d JOIN customers c ON c.id = d.customer_id WHERE d.status = 'open' AND DATEDIFF(CURDATE(), DATE(d.created_at)) >= 14 ORDER BY d.created_at");
    foreach ($stmt->fetchAll() as $debt) {
        $rows[] = [
            'type' => 'ديون',
            'title' => 'دين متأخر',
            'details' => $debt['customer_name'] . ' عليه ' . money($debt['remaining_amount']) . ' منذ ' . $debt['age_days'] . ' يوم',
            'severity' => 'danger',
            'created_at' => $debt['created_at'],
        ];
    }

    $stmt = $db->query("SELECT bt.*, l.name AS location_name, COALESCE((SELECT SUM(total) FROM invoices i WHERE i.location_id = bt.location_id AND DATE(i.created_at) = bt.target_date), 0) AS achieved FROM branch_targets bt JOIN locations l ON l.id = bt.location_id WHERE bt.target_date = CURDATE()");
    foreach ($stmt->fetchAll() as $target) {
        $percent = (float) $target['target_amount'] > 0 ? ((float) $target['achieved'] / (float) $target['target_amount']) * 100 : 0;
        if ((float) $target['target_amount'] > 0 && $percent < 50) {
            $rows[] = [
                'type' => 'مبيعات',
                'title' => 'فرع أقل من التارجت اليومي',
                'details' => $target['location_name'] . ' حقق ' . number_format($percent, 1) . '% من تارجت اليوم',
                'severity' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    $stmt = $db->query("SELECT u.name, l.name AS location_name FROM users u LEFT JOIN locations l ON l.id = u.location_id WHERE u.is_active = 1 AND u.id NOT IN (SELECT user_id FROM attendance_records WHERE action = 'check_in' AND DATE(created_at) = CURDATE()) ORDER BY u.name");
    foreach ($stmt->fetchAll() as $employee) {
        $rows[] = [
            'type' => 'موظفين',
            'title' => 'لم يسجل حضور اليوم',
            'details' => $employee['name'] . ' - ' . ($employee['location_name'] ?: 'عام'),
            'severity' => 'info',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    try {
        foreach (expiring_offers_rows(3) as $offer) {
            $daysLeft = (int)$offer['days_left'];
            $daysText = $daysLeft === 0 ? 'ينتهي اليوم' : ($daysLeft === 1 ? 'ينتهي غداً' : 'ينتهي خلال ' . $daysLeft . ' أيام');
            $rows[] = [
                'type' => 'عروض',
                'title' => 'عرض ترويجي يقترب من الانتهاء ⏳',
                'details' => 'العرض «' . $offer['name'] . '» ' . $daysText . ' (تاريخ الانتهاء: ' . $offer['end_date'] . ')',
                'severity' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
    } catch (Throwable $e) {}

    return $rows;
}

function audit_rows(array $filters = [], int $limit = 200): array
{
    $db = pdo();
    $sql = 'SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1';
    $params = [];

    if (!empty($filters['q'])) {
        $sql .= ' AND (a.details LIKE ? OR a.entity_type LIKE ? OR a.action LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($filters['user_id'])) {
        $sql .= ' AND a.user_id = ?';
        $params[] = (int) $filters['user_id'];
    }

    if (!empty($filters['action_type'])) {
        $sql .= ' AND a.action = ?';
        $params[] = $filters['action_type'];
    }

    if (!empty($filters['start_date'])) {
        $sql .= ' AND DATE(a.created_at) >= ?';
        $params[] = $filters['start_date'];
    }

    if (!empty($filters['end_date'])) {
        $sql .= ' AND DATE(a.created_at) <= ?';
        $params[] = $filters['end_date'];
    }

    $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function log_audit(?int $userId, string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    $stmt = pdo()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId, $details]);
}

function create_online_order(array $data, int $userId): void
{
    if (current_user_location_type() === 'online') {
        throw new RuntimeException('الأونلاين قناة طلبات فقط وليس مخزناً أو فرع بيع.');
    }
    $db = pdo();
    $db->beginTransaction();
    try {
        $customerId = (int) $data['customer_id'];
        if ($customerId <= 0) {
            throw new RuntimeException('اختر عميل أونلاين.');
        }
        $total = 0.0;
        $items = [];
        foreach (($data['product_id'] ?? []) as $idx => $productIdRaw) {
            $productId = (int) $productIdRaw;
            $quantity = (float) ($data['quantity'][$idx] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $product = find_product($productId);
            if (!$product) {
                continue;
            }
            $unitPriceRaw = trim((string) ($data['unit_price'][$idx] ?? ''));
            $unitPrice = $unitPriceRaw !== '' ? (float) $unitPriceRaw : (float) $product['sale_price'];
            $total += $quantity * $unitPrice;
            $items[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice];
        }
        if (!$items) {
            throw new RuntimeException('الطلب لا يحتوي على منتجات.');
        }
        $number = 'ON-' . date('Ymd-His') . '-' . random_int(100, 999);
        $status = $data['status'] ?? 'preparing';
        $allowedStatus = in_array($status, ['preparing', 'shipped', 'delivered', 'cancelled'], true) ? $status : 'preparing';
        $discountType = ($data['discount_type'] ?? '') ?: null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = line_discount($total, $discountType, $discountValue);
        $netTotal = max(0, $total - $discountAmount);
        $stmt = $db->prepare('INSERT INTO online_orders (order_number, customer_id, status, total, payment_method, notes, discount_type, discount_value, discount_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$number, $customerId, $allowedStatus, $netTotal, $data['payment_method'] ?? 'cash', $data['notes'] ?: null, $discountType, $discountValue, $discountAmount]);
        $orderId = (int) $db->lastInsertId();
        foreach ($items as $item) {
            $stmt = $db->prepare('INSERT INTO online_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['unit_price']]);
        }
        log_audit($userId, 'create', 'online_order', $orderId, 'إنشاء طلب أونلاين ' . $number);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_online_order_status(int $orderId, string $status, int $userId): void
{
    if (current_user_location_type() === 'online') {
        throw new RuntimeException('الأونلاين قناة طلبات فقط وليس مخزناً أو فرع بيع.');
    }
    $allowed = ['preparing', 'shipped', 'delivered', 'cancelled'];
    $status = in_array($status, $allowed, true) ? $status : 'preparing';
    $stmt = pdo()->prepare('UPDATE online_orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $orderId]);
    log_audit($userId, 'update_status', 'online_order', $orderId, $status);
}

function find_online_order(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT o.*, c.name AS customer_name, c.phone FROM online_orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $stmt = pdo()->prepare('SELECT * FROM online_order_items WHERE order_id = ?');
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll();
    return $order;
}

function update_online_order(int $orderId, array $data, int $userId): void
{
    if (current_user_location_type() === 'online') {
        throw new RuntimeException('الأونلاين قناة طلبات فقط وليس مخزناً أو فرع بيع.');
    }
    $db = pdo();
    $db->beginTransaction();
    try {
        $order = find_online_order($orderId);
        if (!$order) {
            throw new RuntimeException('الطلب غير موجود.');
        }
        $customerId = (int) $data['customer_id'];
        if ($customerId <= 0) {
            throw new RuntimeException('اختر عميل أونلاين.');
        }
        $total = 0.0;
        $items = [];
        foreach (($data['product_id'] ?? []) as $idx => $productIdRaw) {
            $productId = (int) $productIdRaw;
            $quantity = (float) ($data['quantity'][$idx] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $product = find_product($productId);
            if (!$product) {
                continue;
            }
            $unitPriceRaw = trim((string) ($data['unit_price'][$idx] ?? ''));
            $unitPrice = $unitPriceRaw !== '' ? (float) $unitPriceRaw : (float) $product['sale_price'];
            $total += $quantity * $unitPrice;
            $items[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice];
        }
        if (!$items) {
            throw new RuntimeException('الطلب لا يحتوي على منتجات.');
        }
        $status = $data['status'] ?? 'preparing';
        $allowedStatus = in_array($status, ['preparing', 'shipped', 'delivered', 'cancelled'], true) ? $status : 'preparing';
        $discountType = ($data['discount_type'] ?? '') ?: null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = line_discount($total, $discountType, $discountValue);
        $netTotal = max(0, $total - $discountAmount);
        $stmt = $db->prepare('UPDATE online_orders SET customer_id = ?, status = ?, total = ?, payment_method = ?, notes = ?, discount_type = ?, discount_value = ?, discount_amount = ? WHERE id = ?');
        $stmt->execute([
            $customerId,
            $allowedStatus,
            $netTotal,
            $data['payment_method'] ?? 'cash',
            $data['notes'] ?: null,
            $discountType,
            $discountValue,
            $discountAmount,
            $orderId,
        ]);
        $stmt = $db->prepare('DELETE FROM online_order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);
        foreach ($items as $item) {
            $stmt = $db->prepare('INSERT INTO online_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['unit_price']]);
        }
        log_audit($userId, 'update', 'online_order', $orderId, 'تعديل طلب أونلاين ' . $order['order_number']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_online_order(int $orderId, int $userId): void
{
    $order = find_online_order($orderId);
    if (!$order) {
        throw new RuntimeException('الطلب غير موجود.');
    }
    $stmt = pdo()->prepare('DELETE FROM online_orders WHERE id = ?');
    $stmt->execute([$orderId]);
    log_audit($userId, 'delete', 'online_order', $orderId, 'حذف طلب أونلاين ' . $order['order_number']);
}

function online_order_rows(): array
{
    return pdo()->query('SELECT o.*, c.name AS customer_name, c.phone FROM online_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC, o.id DESC')->fetchAll();
}

function backup_database(int $userId): string
{
    $dir = __DIR__ . '/../storage/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/backup-' . date('Ymd-His') . '.sql';
    $tables = pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- Backup " . DB_NAME . " at " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $table) {
        $create = pdo()->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
        $sql .= "\nDROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n";
        $rows = pdo()->query('SELECT * FROM `' . $table . '`')->fetchAll();
        foreach ($rows as $row) {
            $columns = array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
            $values = array_map(fn($v) => $v === null ? 'NULL' : pdo()->quote((string) $v), array_values($row));
            $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($file, $sql);
    log_audit($userId, 'backup', 'database', null, basename($file));
    return $file;
}

function backup_files(): array
{
    $dir = __DIR__ . '/../storage/backups';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    rsort($files);
    return array_map(fn($file) => [
        'name' => basename($file),
        'size' => filesize($file),
        'created_at' => date('Y-m-d H:i:s', filemtime($file)),
    ], $files);
}

function backup_file_path(string $name): ?string
{
    $safe = basename($name);
    if (!str_ends_with($safe, '.sql')) {
        return null;
    }
    $file = __DIR__ . '/../storage/backups/' . $safe;
    return is_file($file) ? $file : null;
}

function update_recipe(int $recipeId, array $data): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        // Fetch recipe header to find product_id
        $stmt = $db->prepare('SELECT product_id FROM recipe_headers WHERE id = ?');
        $stmt->execute([$recipeId]);
        $productId = (int) $stmt->fetchColumn();

        if ($productId > 0) {
            // Update product
            $stmt = $db->prepare('UPDATE products SET name = ?, sale_price = ? WHERE id = ?');
            $stmt->execute([$data['name'], (float) $data['default_sale_price'], $productId]);
        }

        // Update recipe header
        $stmt = $db->prepare('UPDATE recipe_headers SET name = ?, bottle_product_id = ?, default_sale_price = ? WHERE id = ?');
        $stmt->execute([$data['name'], (int) $data['bottle_product_id'], (float) $data['default_sale_price'], $recipeId]);

        // Delete old components
        $stmt = $db->prepare('DELETE FROM recipe_components WHERE recipe_id = ?');
        $stmt->execute([$recipeId]);

        // Insert new components
        foreach (($data['perfume_product_id'] ?? []) as $idx => $perfumeId) {
            $grams = (float) ($data['grams'][$idx] ?? 0);
            if ((int) $perfumeId > 0 && $grams > 0) {
                $stmt = $db->prepare('INSERT INTO recipe_components (recipe_id, perfume_product_id, grams) VALUES (?, ?, ?)');
                $stmt->execute([$recipeId, (int) $perfumeId, $grams]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_recipe(int $id): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        // Fetch product_id to soft delete it
        $stmt = $db->prepare('SELECT product_id FROM recipe_headers WHERE id = ?');
        $stmt->execute([$id]);
        $productId = (int) $stmt->fetchColumn();
        
        // Soft delete recipe header
        $stmt = $db->prepare('UPDATE recipe_headers SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);

        if ($productId > 0) {
            $stmt = $db->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
            $stmt->execute([$productId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_formula_default(int $id): void
{
    if ($id <= 0) {
        throw new RuntimeException('إعداد الجرامات الافتراضية غير صالح.');
    }
    $stmt = pdo()->prepare('DELETE FROM formula_defaults WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('لم يتم العثور على إعداد الجرامات الافتراضية للحذف.');
    }
}

function delete_formula_defaults_by_bottle(int $bottleId, ?string $qualityGrade = null): void
{
    if ($bottleId <= 0) {
        throw new RuntimeException('الزجاجة غير صالحة.');
    }
    if ($qualityGrade !== null) {
        $stmt = pdo()->prepare('
            DELETE FROM formula_defaults 
            WHERE bottle_product_id = ? 
              AND perfume_product_id IN (
                  SELECT product_id FROM product_perfume_details WHERE COALESCE(quality_grade, "") = ?
              )
        ');
        $stmt->execute([$bottleId, $qualityGrade]);
    } else {
        $stmt = pdo()->prepare('DELETE FROM formula_defaults WHERE bottle_product_id = ?');
        $stmt->execute([$bottleId]);
    }
}

function find_formula_default(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM formula_defaults WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function all_wasted_products(?int $locationId = null): array
{
    $sql = 'SELECT w.*, p.name AS product_name, l.name AS location_name, u.name AS user_name 
            FROM wasted_products w 
            JOIN products p ON p.id = w.product_id 
            JOIN locations l ON l.id = w.location_id 
            JOIN users u ON u.id = w.created_by';
    $params = [];
    if ($locationId !== null) {
        $sql .= ' WHERE w.location_id = ?';
        $params[] = $locationId;
    }
    $sql .= ' ORDER BY w.created_at DESC';
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function add_wasted_product(array $data, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $locationId = (int) $data['location_id'];
        $productId = (int) $data['product_id'];
        $quantity = (float) $data['quantity'];
        $reason = $data['reason'] ?: 'منتج تالف / هالك';

        if ($locationId <= 0 || $productId <= 0 || $quantity <= 0) {
            throw new RuntimeException('يرجى تحديد الموقع والمنتج والكمية بشكل صحيح.');
        }

        $currentStock = get_stock($locationId, $productId);
        if ($currentStock < $quantity) {
            $product = find_product($productId);
            throw new RuntimeException('المخزون غير كافٍ لتسجيل هذا الهالك للمنتج: ' . ($product['name'] ?? ('#' . $productId)) . '. المتاح حالياً: ' . qty($currentStock));
        }

        $stmt = $db->prepare('INSERT INTO wasted_products (location_id, product_id, quantity, reason, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$locationId, $productId, $quantity, $reason, $userId]);
        $wasteId = (int) $db->lastInsertId();

        move_inventory($db, $locationId, $productId, -1 * $quantity, 'manual_adjustment', $userId, 'waste', $wasteId, 'تسجيل هالك: ' . $reason);

        $product = find_product($productId);
        log_audit($userId, 'create', 'waste', $wasteId, 'تسجيل هالك للمنتج ' . ($product['name'] ?? '') . ' بكمية ' . qty($quantity) . ' في موقع #' . $locationId);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function payment_method_labels(): array
{
    return [
        'cash' => 'كاش / نقداً',
        'instapay' => 'إنستا باي',
        'vodafone_cash' => 'فودافون كاش',
    ];
}

function branch_cash_transfer_rows(array $filters = []): array
{
    $db = pdo();
    $sql = 'SELECT t.*, l.name AS location_name, u.name AS created_name, r.name AS received_name
            FROM branch_cash_transfers t
            JOIN locations l ON l.id = t.location_id
            JOIN users u ON u.id = t.created_by
            LEFT JOIN users r ON r.id = t.received_by
            WHERE 1=1';
    $params = [];
    
    if (!empty($filters['location_id'])) {
        $sql .= ' AND t.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND t.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['method'])) {
        $method = (string) $filters['method'];
        if (array_key_exists($method, payment_method_labels())) {
            $sql .= ' AND t.method = ?';
            $params[] = $method;
        }
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND t.transfer_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND t.transfer_date <= ?';
        $params[] = $filters['date_to'];
    }
    
    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generate_collection_number(): string
{
    return 'MGR-' . date('YmdHis') . '-' . random_int(1000, 9999);
}

function manager_auto_collection_source_id(string $date, int $locationId): int
{
    return ((int) str_replace('-', '', $date) * 100000) + $locationId;
}

function sync_manager_auto_collections(?string $date = null, ?int $locationId = null): void
{
    $db = pdo();
    $date = $date ?: date('Y-m-d');
    $locationWhere = $locationId !== null ? ' AND i.location_id = ?' : '';
    $params = [$date];
    if ($locationId !== null) {
        $params[] = $locationId;
    }

    $stmt = $db->prepare('SELECT i.location_id, p.method, COALESCE(SUM(p.amount), 0) AS total
                          FROM payments p
                          JOIN invoices i ON i.id = p.invoice_id
                          WHERE p.method IN ("instapay", "vodafone_cash")
                            AND DATE(p.created_at) = ?' . $locationWhere . '
                          GROUP BY i.location_id, p.method');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $upsert = $db->prepare('INSERT INTO manager_collections
                            (collection_number, location_id, method, source_type, source_id, amount, collection_date, status, created_by)
                            VALUES (?, ?, ?, "auto_payment", ?, ?, ?, "pending", NULL)
                            ON DUPLICATE KEY UPDATE
                                amount = IF(status = "pending", VALUES(amount), amount),
                                collection_date = VALUES(collection_date),
                                location_id = VALUES(location_id)');
    foreach ($rows as $row) {
        $locId = (int) $row['location_id'];
        $upsert->execute([
            generate_collection_number(),
            $locId,
            (string) $row['method'],
            manager_auto_collection_source_id($date, $locId),
            (float) $row['total'],
            $date,
        ]);
    }
}

function upsert_branch_transfer_manager_collection(array $transfer, ?int $receivedBy = null): void
{
    $db = pdo();
    $status = $transfer['status'] === 'received' ? 'received' : ($transfer['status'] === 'cancelled' ? 'cancelled' : 'pending');
    $receivedAtSql = $status === 'received' ? 'COALESCE(VALUES(received_at), received_at)' : 'NULL';
    $stmt = $db->prepare('INSERT INTO manager_collections
                          (collection_number, location_id, method, source_type, source_id, amount, collection_date, status, notes, created_by, received_by, received_at)
                          VALUES (?, ?, ?, "branch_transfer", ?, ?, ?, ?, ?, ?, ?, ' . ($status === 'received' ? 'NOW()' : 'NULL') . ')
                          ON DUPLICATE KEY UPDATE
                              location_id = VALUES(location_id),
                              amount = VALUES(amount),
                              collection_date = VALUES(collection_date),
                              status = VALUES(status),
                              notes = VALUES(notes),
                              received_by = VALUES(received_by),
                              received_at = ' . $receivedAtSql);
    $stmt->execute([
        generate_collection_number(),
        (int) $transfer['location_id'],
        (string) $transfer['method'],
        (int) $transfer['id'],
        (float) $transfer['amount'],
        (string) $transfer['transfer_date'],
        $status,
        $transfer['notes'] ?? null,
        isset($transfer['created_by']) ? (int) $transfer['created_by'] : null,
        $receivedBy,
    ]);
}

function manager_collection_rows(array $filters = []): array
{
    $db = pdo();
    $sql = 'SELECT c.*, l.name AS location_name, u.name AS created_name, r.name AS received_name
            FROM manager_collections c
            JOIN locations l ON l.id = c.location_id
            LEFT JOIN users u ON u.id = c.created_by
            LEFT JOIN users r ON r.id = c.received_by
            WHERE 1=1';
    $params = [];

    if (!empty($filters['location_id'])) {
        $sql .= ' AND c.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }
    if (!empty($filters['method']) && array_key_exists((string) $filters['method'], payment_method_labels())) {
        $sql .= ' AND c.method = ?';
        $params[] = (string) $filters['method'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND c.status = ?';
        $params[] = (string) $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND c.collection_date >= ?';
        $params[] = (string) $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND c.collection_date <= ?';
        $params[] = (string) $filters['date_to'];
    }

    $sql .= ' ORDER BY c.collection_date DESC, c.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function manager_treasury_expense_total(array $filters = []): float
{
    return array_sum(manager_treasury_expense_totals($filters));
}

function manager_treasury_expense_totals(array $filters = []): array
{
    $sql = 'SELECT payment_method, COALESCE(SUM(amount), 0) AS total FROM expenses WHERE location_id IS NULL';
    $params = [];

    if (!empty($filters['date_from'])) {
        $sql .= ' AND expense_date >= ?';
        $params[] = (string) $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND expense_date <= ?';
        $params[] = (string) $filters['date_to'];
    }
    $sql .= ' GROUP BY payment_method';
    
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    $totals = [];
    foreach ($stmt->fetchAll() as $row) {
        $totals[$row['payment_method']] = (float) $row['total'];
    }
    return $totals;
}

function manager_treasury_summary(array $filters = []): array
{
    $rows = manager_collection_rows($filters);
    $methods = array_keys(payment_method_labels());
    $summary = ['received_total' => 0.0, 'expense_total' => 0.0, 'net_cash' => 0.0, 'net_total' => 0.0];
    foreach ($methods as $method) {
        $summary[$method] = ['pending' => 0.0, 'received' => 0.0, 'cancelled' => 0.0];
    }

    foreach ($rows as $row) {
        $method = (string) $row['method'];
        $status = (string) $row['status'];
        if (!isset($summary[$method][$status])) {
            continue;
        }
        $amount = (float) $row['amount'];
        $summary[$method][$status] += $amount;
        if ($status === 'received') {
            $summary['received_total'] += $amount;
        }
    }

    $expenseTotals = manager_treasury_expense_totals($filters);

    // Fetch salary payments made from manager treasury (location_id IS NULL)
    $sqlSalaries = "SELECT payment_method, COALESCE(SUM(net_payout), 0) AS total FROM salary_payments WHERE location_id IS NULL";
    $salParams = [];
    if (!empty($filters['date_from'])) {
        $sqlSalaries .= ' AND paid_at >= ?';
        $salParams[] = (string) $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $sqlSalaries .= ' AND paid_at <= ?';
        $salParams[] = (string) $filters['date_to'] . ' 23:59:59';
    }
    $sqlSalaries .= ' GROUP BY payment_method';
    $stmtSal = pdo()->prepare($sqlSalaries);
    $stmtSal->execute($salParams);
    foreach ($stmtSal->fetchAll() as $row) {
        $method = $row['payment_method'];
        if (!isset($expenseTotals[$method])) {
            $expenseTotals[$method] = 0.0;
        }
        $expenseTotals[$method] += (float) $row['total'];
    }

    $summary['expense_total'] = array_sum($expenseTotals);
    
    // Deduct expenses from respective payment method received amounts
    foreach ($methods as $method) {
        $summary[$method]['expense'] = $expenseTotals[$method] ?? 0.0;
        $summary[$method]['net'] = ($summary[$method]['received'] ?? 0.0) - $summary[$method]['expense'];
    }
    
    $summary['net_cash'] = $summary['cash']['net'];
    $summary['net_total'] = $summary['received_total'] - $summary['expense_total'];

    return $summary;
}

function receive_manager_collection(int $id, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM manager_collections WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $collection = $stmt->fetch();
        if (!$collection) {
            throw new RuntimeException('تحصيل خزينة المدير غير موجود.');
        }
        if ($collection['status'] !== 'pending') {
            throw new RuntimeException('لا يمكن تأكيد تحصيل غير معلق.');
        }

        $stmt = $db->prepare('UPDATE manager_collections SET status = "received", received_by = ?, received_at = NOW() WHERE id = ?');
        $stmt->execute([$userId, $id]);

        if ($collection['source_type'] === 'branch_transfer' && !empty($collection['source_id'])) {
            $stmt = $db->prepare('UPDATE branch_cash_transfers SET status = "received", received_by = ?, received_at = NOW() WHERE id = ? AND status = "pending"');
            $stmt->execute([$userId, (int) $collection['source_id']]);
        }

        log_audit($userId, 'update', 'manager_collection', $id, 'تأكيد استلام خزينة المدير بمبلغ ' . $collection['amount']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function cancel_manager_collection(int $id, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM manager_collections WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $collection = $stmt->fetch();
        if (!$collection) {
            throw new RuntimeException('تحصيل خزينة المدير غير موجود.');
        }
        if ($collection['status'] !== 'pending') {
            throw new RuntimeException('لا يمكن إلغاء تحصيل غير معلق.');
        }

        $stmt = $db->prepare('UPDATE manager_collections SET status = "cancelled" WHERE id = ?');
        $stmt->execute([$id]);
        if ($collection['source_type'] === 'branch_transfer' && !empty($collection['source_id'])) {
            $stmt = $db->prepare('UPDATE branch_cash_transfers SET status = "cancelled" WHERE id = ? AND status = "pending"');
            $stmt->execute([(int) $collection['source_id']]);
        }

        log_audit($userId, 'delete', 'manager_collection', $id, 'إلغاء تحصيل خزينة المدير بمبلغ ' . $collection['amount']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function create_branch_cash_transfer(array $data, int $userId): void
{
    $db = pdo();
    $db->beginTransaction();
    try {
        $locationId = (int) $data['location_id'];
        $method = (string) $data['method'];
        $amount = (float) $data['amount'];
        $transferDate = (string) ($data['transfer_date'] ?: date('Y-m-d'));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($locationId <= 0) {
            throw new RuntimeException('يرجى تحديد الفرع.');
        }
        require_location_access($locationId);
        if (!array_key_exists($method, payment_method_labels())) {
            throw new RuntimeException('طريقة التحويل غير صحيحة.');
        }
        if ($method !== 'cash') {
            throw new RuntimeException('إنستا باي وفودافون كاش تتحول تلقائياً لخزينة المدير ولا تحتاج تحويل يدوي.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('يرجى إدخال مبلغ صحيح.');
        }

        $balances = branch_treasury_balances($locationId);
        if (($balances[$method]['balance'] ?? 0) + 0.0001 < $amount) {
            throw new RuntimeException('رصيد الخزينة غير كافٍ لإتمام التحويل.');
        }
        
        $transferNumber = generate_transfer_number();
        
        $stmt = $db->prepare('INSERT INTO branch_cash_transfers (transfer_number, location_id, method, amount, transfer_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$transferNumber, $locationId, $method, $amount, $transferDate, $notes, $userId]);
        $transferId = (int) $db->lastInsertId();
        upsert_branch_transfer_manager_collection([
            'id' => $transferId,
            'location_id' => $locationId,
            'method' => $method,
            'amount' => $amount,
            'transfer_date' => $transferDate,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $userId,
        ]);
        
        $location = find_location_any($locationId);
        log_audit($userId, 'create', 'branch_cash_transfer', $transferId, 'تحويل ' . $amount . ' ج.م من ' . ($location['name'] ?? '') . ' بطريقة ' . $method);
        
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function fetch_pending_branch_cash_transfer(PDO $db, int $transferId, string $statusError): array
{
    $stmt = $db->prepare('SELECT * FROM branch_cash_transfers WHERE id = ? FOR UPDATE');
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch();

    if (!$transfer) {
        throw new RuntimeException('التحويل غير موجود.');
    }
    if ($transfer['status'] !== 'pending') {
        throw new RuntimeException($statusError);
    }

    return $transfer;
}

function update_branch_cash_transfer_status(int $transferId, string $status, int $userId): void
{
    $statusActions = [
        'received' => [
            'status_error' => 'لا يمكن استلام تحويل لم يكن قيد التحويل.',
            'sql' => 'UPDATE branch_cash_transfers SET status = ?, received_by = ?, received_at = NOW() WHERE id = ?',
            'params' => fn () => ['received', $userId, $transferId],
            'audit_action' => 'update',
            'audit_details' => 'استلام تحويل بمبلغ ',
        ],
        'cancelled' => [
            'status_error' => 'لا يمكن إلغاء تحويل لم يكن قيد التحويل.',
            'sql' => 'UPDATE branch_cash_transfers SET status = ? WHERE id = ?',
            'params' => fn () => ['cancelled', $transferId],
            'audit_action' => 'delete',
            'audit_details' => 'إلغاء تحويل بمبلغ ',
        ],
    ];

    if (!isset($statusActions[$status])) {
        throw new RuntimeException('حالة التحويل غير صحيحة.');
    }

    $action = $statusActions[$status];
    $db = pdo();
    $db->beginTransaction();
    try {
        $transfer = fetch_pending_branch_cash_transfer($db, $transferId, $action['status_error']);

        $stmt = $db->prepare($action['sql']);
        $stmt->execute($action['params']());

        $transfer['status'] = $status;
        upsert_branch_transfer_manager_collection($transfer, $status === 'received' ? $userId : null);

        log_audit($userId, $action['audit_action'], 'branch_cash_transfer', $transferId, $action['audit_details'] . $transfer['amount']);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function receive_branch_cash_transfer(int $transferId, int $userId): void
{
    update_branch_cash_transfer_status($transferId, 'received', $userId);
}

function cancel_branch_cash_transfer(int $transferId, int $userId): void
{
    update_branch_cash_transfer_status($transferId, 'cancelled', $userId);
}

function generate_transfer_number(): string
{
    return 'TRF-' . date('YmdHis') . '-' . random_int(1000, 9999);
}

function financial_ledger_rows(array $filters = []): array
{
    $db = pdo();

    $queries = [];
    $allParams = [];
    $sourceType = (string) ($filters['source_type'] ?? '');

    $appendLedgerFilters = static function (string $sql, array $columns, ?string $fixedMethod = null) use ($filters): array {
        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(' . $columns['date'] . ') >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(' . $columns['date'] . ') <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['location_id'])) {
            $sql .= ' AND ' . $columns['location'] . ' = ?';
            $params[] = (int) $filters['location_id'];
        }
        if (!empty($filters['method'])) {
            if ($fixedMethod !== null) {
                return $filters['method'] === $fixedMethod ? [$sql, $params] : [null, []];
            }

            $sql .= ' AND ' . $columns['method'] . ' = ?';
            $params[] = $filters['method'];
        }

        return [$sql, $params];
    };

    $addLedgerQuery = function (string $source, string $sql, array $columns, ?string $fixedMethod = null) use (&$queries, &$allParams, $sourceType, $appendLedgerFilters): void {
        if ($sourceType !== '' && $sourceType !== $source) {
            return;
        }

        [$filteredSql, $params] = $appendLedgerFilters($sql, $columns, $fixedMethod);
        if ($filteredSql === null) {
            return;
        }

        $queries[] = '(' . $filteredSql . ')';
        $allParams = array_merge($allParams, $params);
    };

    $paymentSQL = 'SELECT p.created_at, i.location_id, l.name AS location_name, "in" AS direction, p.method, p.amount, "payment" AS source_type, p.id AS source_id, CONCAT("دفع فاتورة ", i.invoice_number) AS description, u.name AS user_name
                  FROM payments p
                  JOIN users u ON u.id = p.created_by
                  JOIN invoices i ON i.id = p.invoice_id
                  JOIN locations l ON l.id = i.location_id
                  WHERE p.method IN ("cash", "instapay", "vodafone_cash")';
    $addLedgerQuery('payment', $paymentSQL, [
        'date' => 'p.created_at',
        'location' => 'i.location_id',
        'method' => 'p.method',
    ]);
    
    $expenseSQL = 'SELECT e.created_at, e.location_id, l.name AS location_name, "out" AS direction, e.payment_method AS method, e.amount, "expense" AS source_type, e.id AS source_id, CONCAT(ec.name, ": ", COALESCE(e.notes, "")) AS description, u.name AS user_name
                  FROM expenses e
                  JOIN expense_categories ec ON ec.id = e.category_id
                  JOIN users u ON u.id = e.created_by
                  LEFT JOIN locations l ON l.id = e.location_id
                  WHERE 1=1';
    $addLedgerQuery('expense', $expenseSQL, [
        'date' => 'e.created_at',
        'location' => 'e.location_id',
        'method' => 'e.payment_method',
    ]);
    
    $transferSQL = 'SELECT t.created_at, t.location_id, l.name AS location_name, "out" AS direction, t.method, t.amount, "transfer" AS source_type, t.id AS source_id, CONCAT(t.transfer_number, ": ", COALESCE(t.notes, "")) AS description, u.name AS user_name
                   FROM branch_cash_transfers t
                   JOIN locations l ON l.id = t.location_id
                   JOIN users u ON u.id = t.created_by
                   WHERE t.status IN ("pending", "received")';
    $addLedgerQuery('transfer', $transferSQL, [
        'date' => 't.created_at',
        'location' => 't.location_id',
        'method' => 't.method',
    ]);
    
    $returnSQL = 'SELECT r.created_at, i.location_id, l.name AS location_name, "out" AS direction, r.refund_method AS method, r.refund_paid AS amount, "return" AS source_type, r.id AS source_id, CONCAT(r.return_number, ": مرتجع فاتورة ", i.invoice_number) AS description, u.name AS user_name
                 FROM return_invoices r
                 JOIN invoices i ON i.id = r.original_invoice_id
                 JOIN locations l ON l.id = i.location_id
                 JOIN users u ON u.id = r.created_by
                 WHERE r.refund_method IN ("cash", "instapay", "vodafone_cash")';
    $addLedgerQuery('return', $returnSQL, [
        'date' => 'r.created_at',
        'location' => 'i.location_id',
        'method' => 'r.refund_method',
    ]);

    $salarySQL = 'SELECT s.paid_at AS created_at, s.location_id, l.name AS location_name, "out" AS direction, s.payment_method AS method, s.net_payout AS amount, "salary" AS source_type, s.id AS source_id, CONCAT("راتب ", u.name, " - ", s.payroll_month) AS description, admin.name AS user_name
                  FROM salary_payments s
                  JOIN users u ON u.id = s.user_id
                  JOIN users admin ON admin.id = s.paid_by
                  LEFT JOIN locations l ON l.id = s.location_id
                  WHERE 1=1';
    $addLedgerQuery('salary', $salarySQL, [
        'date' => 's.paid_at',
        'location' => 's.location_id',
        'method' => 's.payment_method',
    ]);

    if (empty($queries)) {
        return [];
    }
    
    $unionSQL = implode(' UNION ALL ', $queries) . ' ORDER BY created_at DESC';
    
    $stmt = $db->prepare($unionSQL);
    $stmt->execute($allParams);
    return $stmt->fetchAll();
}


function financial_ledger_summary(array $filters = []): array
{
    $rows = financial_ledger_rows($filters);
    
    $totalIn = 0;
    $totalOut = 0;
    
    foreach ($rows as $row) {
        $amount = (float) $row['amount'];
        if ($row['direction'] === 'in') {
            $totalIn += $amount;
        } else {
            $totalOut += $amount;
        }
    }
    
    return [
        'in' => $totalIn,
        'out' => $totalOut,
        'net' => $totalIn - $totalOut,
    ];
}

/**
 * إرسال رسالة نصية عبر خدمة الواتساب (مرفق معها لوجو البراند تلقائياً)
 */
function send_whatsapp_message(string $phone, string $message, bool $attachLogo = true): bool
{
    $phone = preg_replace('/\D/', '', $phone);
    if (empty($phone)) {
        return false;
    }
    // تنسيق الرقم المصري إذا بدأ بـ 01
    if (strlen($phone) === 11 && str_starts_with($phone, '01')) {
        $phone = '2' . $phone;
    }

    $endpoints = [];
    $customUrl = trim((string)setting_value('whatsapp_api_url', ''));
    if (!empty($customUrl)) {
        $endpoints[] = $customUrl;
    }
    $endpoints[] = 'https://erp.transyshub.tech/api/send-message';
    $endpoints[] = 'http://127.0.0.1:3002/api/send-message';
    $endpoints[] = 'http://localhost:3002/api/send-message';

    $payloadData = [
        'phone' => $phone,
        'message' => $message,
    ];

    if ($attachLogo && setting_value('whatsapp_attach_logo', '1') !== '0') {
        static $cachedLogoBase64 = null;
        if ($cachedLogoBase64 === null) {
            $logoCandidates = [
                dirname(__DIR__) . '/assets/whatsapp_logo.png',
                dirname(__DIR__) . '/assets/logo.png',
            ];
            foreach ($logoCandidates as $cand) {
                if (file_exists($cand)) {
                    $c = @file_get_contents($cand);
                    if ($c) {
                        $cachedLogoBase64 = base64_encode($c);
                        break;
                    }
                }
            }
            if ($cachedLogoBase64 === null) {
                $cachedLogoBase64 = '';
            }
        }
        if (!empty($cachedLogoBase64)) {
            $payloadData['imageBase64'] = $cachedLogoBase64;
        }
        $payloadData['attachLogo'] = true;
    }

    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

    foreach ($endpoints as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['success'])) {
                return true;
            }
        }
    }
    return false;
}

/**
 * تجهيز وإرسال تفاصيل الفاتورة تلقائياً للعميل عبر الواتساب
 */
function send_invoice_whatsapp(int $invoiceId): array
{
    if (setting_value('whatsapp_invoice_enabled', '1') === '0') {
        return ['success' => false, 'error' => 'إرسال الفواتير عبر الواتساب معطل في الإعدادات'];
    }

    $invoice = find_invoice($invoiceId);
    if (!$invoice) {
        return ['success' => false, 'error' => 'الفاتورة غير موجودة'];
    }

    if (empty($invoice['customer_phone'])) {
        return ['success' => false, 'error' => 'العميل ليس لديه رقم هاتف مسجل'];
    }

    $lines = invoice_lines_rows($invoiceId);
    $customerName = !empty($invoice['customer_name']) ? trim($invoice['customer_name']) : 'عميلنا العزيز';
    $branchName = !empty($invoice['location_name']) ? trim($invoice['location_name']) : 'الفرع';
    $storeName = setting_value('shop_name', 'حمزة للعطور');
    $dateStr = date('Y-m-d h:i A', strtotime($invoice['created_at']));

    $msg = "مرحباً بك أستاذ/ة *{$customerName}* 🌹\n";
    $msg .= "شكراً لزيارتك لـ *{$storeName}* ({$branchName}) ✨\n\n";
    $msg .= "📄 *فاتورة مبيعات رقم:* #{$invoice['invoice_number']}\n";
    $msg .= "⏰ *التاريخ:* {$dateStr}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🛍️ *تفاصيل المشتريات:*\n";

    foreach ($lines as $idx => $line) {
        $desc = trim($line['description']);
        $qty = (float)$line['quantity'];
        $price = number_format((float)$line['unit_price'], 2);
        $total = number_format((float)$line['line_total'], 2);
        $num = $idx + 1;
        $msg .= "{$num}. {$desc}\n";
        $msg .= "   ↳ الكمية: {$qty} × {$price} = *{$total} ج.م*\n";
    }

    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $subtotal = number_format((float)$invoice['subtotal'], 2);
    $msg .= "💵 *الإجمالي:* {$subtotal} ج.م\n";

    if ((float)$invoice['discount_amount'] > 0) {
        $discount = number_format((float)$invoice['discount_amount'], 2);
        $msg .= "🎁 *الخصم:* -{$discount} ج.م\n";
    }

    $netTotal = number_format((float)$invoice['total'], 2);
    $msg .= "🏷️ *الصافي المستحق:* *{$netTotal} ج.م*\n";

    $paid = number_format((float)$invoice['paid_total'], 2);
    $msg .= "✅ *المدفوع:* {$paid} ج.م\n";

    if ((float)$invoice['due_total'] > 0) {
        $due = number_format((float)$invoice['due_total'], 2);
        $msg .= "⚠️ *المتبقي (آجل):* *{$due} ج.م*\n";
    }

    $msg .= "\nنتشرف دائماً بخدمتك ونتمنى لك يوماً معطراً وجميلاً! 🌟";

    $sent = send_whatsapp_message($invoice['customer_phone'], $msg);
    if ($sent) {
        return ['success' => true, 'message' => 'تم إرسال الفاتورة بنجاح إلى رقم ' . $invoice['customer_phone']];
    } else {
        return ['success' => false, 'error' => 'تعذر إرسال الرسالة عبر الواتساب'];
    }
}

/**
 * جلب قائمة العملاء الذين يصادف عيد ميلادهم اليوم مع حالة الإرسال
 */
function today_birthday_customers(): array
{
    $stmt = pdo()->query("
        SELECT c.*, 
               loc.name AS location_name,
               l.sent_at AS last_sent_at,
               l.message AS last_message,
               CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END AS already_sent
        FROM customers c
        LEFT JOIN locations loc ON loc.id = c.location_id
        LEFT JOIN customer_birthday_logs l ON l.customer_id = c.id AND l.sent_year = YEAR(CURDATE())
        WHERE c.is_active = 1 
          AND c.birthdate IS NOT NULL 
          AND c.phone IS NOT NULL 
          AND TRIM(c.phone) <> ''
          AND DATE_FORMAT(c.birthdate, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
        ORDER BY c.name ASC
    ");
    return $stmt->fetchAll();
}

/**
 * جلب قائمة العملاء الذين تصادف أعياد ميلادهم في شهر معين
 */
function month_birthday_customers(?int $month = null): array
{
    $month = $month ?: (int)date('m');
    $stmt = pdo()->prepare("
        SELECT c.*, 
               loc.name AS location_name,
               l.sent_at AS last_sent_at,
               CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END AS already_sent,
               DAY(c.birthdate) AS birth_day
        FROM customers c
        LEFT JOIN locations loc ON loc.id = c.location_id
        LEFT JOIN customer_birthday_logs l ON l.customer_id = c.id AND l.sent_year = YEAR(CURDATE())
        WHERE c.is_active = 1 
          AND c.birthdate IS NOT NULL 
          AND c.phone IS NOT NULL 
          AND TRIM(c.phone) <> ''
          AND MONTH(c.birthdate) = ?
        ORDER BY DAY(c.birthdate) ASC, c.name ASC
    ");
    $stmt->execute([$month]);
    return $stmt->fetchAll();
}

/**
 * جلب سجل رسائل أعياد الميلاد المرسلة
 */
function birthday_logs_history(int $limit = 50): array
{
    $stmt = pdo()->prepare("
        SELECT l.*, c.name AS customer_name, loc.name AS location_name
        FROM customer_birthday_logs l
        LEFT JOIN customers c ON c.id = l.customer_id
        LEFT JOIN locations loc ON loc.id = c.location_id
        ORDER BY l.sent_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * إرسال تهنئة عيد ميلاد لعميل محدد بالاسم
 */
function send_single_customer_birthday_whatsapp(int $customerId, ?string $customMessage = null): array
{
    $db = pdo();
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $cust = $stmt->fetch();

    if (!$cust || empty(trim((string)$cust['phone']))) {
        return ['success' => false, 'error' => 'العميل غير موجود أو لا يملك رقم هاتف مسجل'];
    }

    $customerName = trim($cust['name']);
    $phone = trim($cust['phone']);
    $storeName = setting_value('shop_name', 'حمزة للعطور');

    if ($customMessage !== null && trim($customMessage) !== '') {
        $msg = $customMessage;
    } else {
        $defaultTemplate = "كل عام وأنت بخير وبصحة وسعادة أستاذ/ة {name} 🎂🌹\nأسرة *{shop_name}* تتمنى لك عاماً سعيداً مليئاً بالبهجة والنجاح والبركة! ✨\n🎁 بهذه المناسبة السعيدة، يسعدنا أن نهديك خصم خاص 20% على أي زجاجة عطر من اختيارك عند زيارتك القادمة لنا! 🎉";
        $template = setting_value('whatsapp_birthday_message', $defaultTemplate);
        if (empty(trim((string)$template))) {
            $template = $defaultTemplate;
        }
        $msg = str_replace(
            ['{name}', '{phone}', '{shop_name}'],
            [$customerName, $phone, $storeName],
            $template
        );
    }

    $sent = send_whatsapp_message($phone, $msg);
    if ($sent) {
        $currentYear = (int)date('Y');
        $stmtLog = $db->prepare("
            INSERT INTO customer_birthday_logs (customer_id, sent_year, phone, message) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE sent_at = NOW(), message = VALUES(message)
        ");
        $stmtLog->execute([$customerId, $currentYear, $phone, $msg]);

        $user = function_exists('current_user') ? current_user() : null;
        $userId = $user ? (int) ($user['id'] ?? 0) : 0;
        if ($userId > 0) {
            log_audit($userId, 'send', 'whatsapp_birthday', $customerId, 'إرسال تهنئة عيد ميلاد للعميل: ' . $customerName);
        }

        return ['success' => true, 'message' => 'تم إرسال تهنئة عيد الميلاد بنجاح للعميل ' . $customerName];
    } else {
        return ['success' => false, 'error' => 'تعذر إرسال الرسالة عبر الواتساب. تأكد من اتصال الخدمة.'];
    }
}

/**
 * معالجة وإرسال رسائل تهنئة عيد الميلاد لجميع عملاء اليوم
 */
function process_daily_birthday_whatsapp(bool $forceResend = false): array
{
    $db = pdo();

    $defaultTemplate = "كل عام وأنت بخير وبصحة وسعادة أستاذ/ة {name} 🎂🌹\nأسرة *{shop_name}* تتمنى لك عاماً سعيداً مليئاً بالبهجة والنجاح والبركة! ✨\n🎁 بهذه المناسبة السعيدة، يسعدنا أن نهديك خصم خاص 20% على أي زجاجة عطر من اختيارك عند زيارتك القادمة لنا! 🎉";
    $template = setting_value('whatsapp_birthday_message', $defaultTemplate);
    if (empty(trim((string)$template))) {
        $template = $defaultTemplate;
    }
    $storeName = setting_value('shop_name', 'حمزة للعطور');

    $customers = today_birthday_customers();
    $sentCount = 0;
    $failedCount = 0;
    $skippedCount = 0;
    $sentNames = [];
    $currentYear = (int)date('Y');

    foreach ($customers as $cust) {
        if (!$forceResend && !empty($cust['already_sent'])) {
            $skippedCount++;
            continue; // تم إرسال التهنئة له هذا العام بالفعل
        }

        $customerName = trim($cust['name']);
        $phone = trim($cust['phone']);

        $msg = str_replace(
            ['{name}', '{phone}', '{shop_name}'],
            [$customerName, $phone, $storeName],
            $template
        );

        $sent = send_whatsapp_message($phone, $msg);
        if ($sent) {
            $sentCount++;
            $sentNames[] = $customerName;
            $stmt = $db->prepare("INSERT INTO customer_birthday_logs (customer_id, sent_year, phone, message) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE sent_at = NOW(), message = VALUES(message)");
            $stmt->execute([(int)$cust['id'], $currentYear, $phone, $msg]);
        } else {
            $failedCount++;
        }
    }

    return [
        'total_today' => count($customers),
        'sent_count' => $sentCount,
        'skipped_count' => $skippedCount,
        'failed_count' => $failedCount,
        'sent_names' => $sentNames
    ];
}

/**
 * جدولة استبيان رضا العميل بعد الفاتورة بتأخير زمني محدد
 */
function schedule_invoice_satisfaction_survey(int $invoiceId): ?int
{
    if (setting_value('whatsapp_survey_enabled', '1') === '0') {
        return null;
    }

    $db = pdo();
    $stmt = $db->prepare("
        SELECT i.id, i.customer_id, i.location_id, c.phone, c.name AS customer_name
        FROM invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch();

    if (!$inv || empty(trim((string)$inv['phone']))) {
        return null; // لا يوجد رقم هاتف للعميل أو زبون عابر بدون هاتف
    }

    // التحقق من عدم وجود استبيان مجدول مسبقاً لنفس الفاتورة
    $chk = $db->prepare("SELECT id FROM customer_survey_queue WHERE invoice_id = ? LIMIT 1");
    $chk->execute([$invoiceId]);
    if ($chk->fetch()) {
        return null;
    }

    $delayMinutes = max(1, (int) setting_value('whatsapp_survey_delay_minutes', '10'));
    $scheduledAt = date('Y-m-d H:i:s', time() + ($delayMinutes * 60));
    $token = bin2hex(random_bytes(16));

    $stmtIns = $db->prepare("
        INSERT INTO customer_survey_queue (invoice_id, customer_id, phone, token, scheduled_at, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmtIns->execute([
        $invoiceId,
        $inv['customer_id'] ? (int)$inv['customer_id'] : null,
        trim((string)$inv['phone']),
        $token,
        $scheduledAt
    ]);

    return (int) $db->lastInsertId();
}

/**
 * بناء نص رسالة تقييم رضا العميل بعد الفاتورة مع الأزرار والروابط التفاعلية
 */
function build_survey_whatsapp_message(array $survey): string
{
    $defaultSurveyMsg = "عميلنا العزيز أستاذ/ة {name} 🌹\nشكراً لزيارتك لفرعنا *{branch_name}* وتسوقك معنا اليوم! 🛍️✨\nراحتك ورضاك هي أولويتنا دائماً، ويهمنا جداً معرفة رأيك في خدمتنا:\n\nهل كانت تجربة الشراء والخدمة في الفرع مرضية لك؟\n\n🟢 راضٍ عن الخدمة 👍:\n{satisfied_link}\n\n🔴 غير راضٍ عن الخدمة 👎:\n{unsatisfied_link}\n\nأسرة {shop_name} تتمنى لك يوماً سعيداً ومعطراً! 🌟";
    $template = setting_value('whatsapp_survey_message', $defaultSurveyMsg);
    if (empty(trim((string)$template))) {
        $template = $defaultSurveyMsg;
    }

    $storeName = setting_value('shop_name', 'حمزة للعطور');
    $customerName = !empty($survey['customer_name']) ? trim($survey['customer_name']) : 'العميل';
    $branchName = !empty($survey['location_name']) ? trim($survey['location_name']) : $storeName;
    $invoiceNum = $survey['invoice_number'] ?? ('#' . $survey['invoice_id']);

    $baseUrl = rtrim((string)setting_value('app_url', 'https://erp.alulaprint.com'), '/');
    $satLink = "{$baseUrl}/survey.php?t={$survey['token']}&r=1";
    $unsatLink = "{$baseUrl}/survey.php?t={$survey['token']}&r=0";

    return str_replace(
        ['{name}', '{branch_name}', '{shop_name}', '{invoice_number}', '{satisfied_link}', '{unsatisfied_link}'],
        [$customerName, $branchName, $storeName, $invoiceNum, $satLink, $unsatLink],
        $template
    );
}

/**
 * معالجة طابور استبيانات رضا العملاء وإرسال الرسائل التي حان موعدها
 */
function process_survey_queue(int $limit = 25): array
{
    if (setting_value('whatsapp_survey_enabled', '1') === '0') {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    }

    $db = pdo();
    $stmt = $db->prepare("
        SELECT q.*, 
               i.invoice_number, 
               c.name AS customer_name, 
               loc.name AS location_name
        FROM customer_survey_queue q
        JOIN invoices i ON i.id = q.invoice_id
        LEFT JOIN customers c ON c.id = q.customer_id
        LEFT JOIN locations loc ON loc.id = i.location_id
        WHERE q.status = 'pending' AND q.scheduled_at <= NOW()
        ORDER BY q.scheduled_at ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $sentCount = 0;
    $failedCount = 0;

    foreach ($rows as $survey) {
        $msg = build_survey_whatsapp_message($survey);
        $sent = send_whatsapp_message($survey['phone'], $msg);

        if ($sent) {
            $sentCount++;
            $u = $db->prepare("UPDATE customer_survey_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $u->execute([(int)$survey['id']]);
        } else {
            $failedCount++;
            $u = $db->prepare("UPDATE customer_survey_queue SET status = 'failed' WHERE id = ?");
            $u->execute([(int)$survey['id']]);
        }
    }

    return [
        'processed' => count($rows),
        'sent' => $sentCount,
        'failed' => $failedCount
    ];
}

/**
 * إرسال رسالة استبيان واحدة محددة فوراً من الطابور
 */
function send_single_queued_survey(int $surveyQueueId): array
{
    $db = pdo();
    $stmt = $db->prepare("
        SELECT q.*, 
               i.invoice_number, 
               c.name AS customer_name, 
               loc.name AS location_name
        FROM customer_survey_queue q
        JOIN invoices i ON i.id = q.invoice_id
        LEFT JOIN customers c ON c.id = q.customer_id
        LEFT JOIN locations loc ON loc.id = i.location_id
        WHERE q.id = ?
    ");
    $stmt->execute([$surveyQueueId]);
    $survey = $stmt->fetch();

    if (!$survey) {
        return ['success' => false, 'error' => 'الرسالة غير موجودة في الطابور'];
    }

    $msg = build_survey_whatsapp_message($survey);
    $sent = send_whatsapp_message($survey['phone'], $msg);

    if ($sent) {
        $u = $db->prepare("UPDATE customer_survey_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $u->execute([$surveyQueueId]);
        return ['success' => true, 'message' => 'تم إرسال استبيان الرضا بنجاح إلى ' . $survey['phone']];
    } else {
        $u = $db->prepare("UPDATE customer_survey_queue SET status = 'failed' WHERE id = ?");
        $u->execute([$surveyQueueId]);
        return ['success' => false, 'error' => 'تعذر إرسال الرسالة عبر الواتساب'];
    }
}

/**
 * إحصائيات تقييمات العملاء ومعدل الرضا
 */
function get_survey_stats(): array
{
    $db = pdo();
    $totalSent = (int)$db->query("SELECT COUNT(*) FROM customer_survey_queue WHERE status = 'sent'")->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM customer_survey_queue WHERE status = 'pending'")->fetchColumn();
    $satisfiedCount = (int)$db->query("SELECT COUNT(*) FROM customer_survey_queue WHERE rating = 'satisfied'")->fetchColumn();
    $unsatisfiedCount = (int)$db->query("SELECT COUNT(*) FROM customer_survey_queue WHERE rating = 'unsatisfied'")->fetchColumn();
    $totalRated = $satisfiedCount + $unsatisfiedCount;
    $satisfactionRate = $totalRated > 0 ? round(($satisfiedCount / $totalRated) * 100, 1) : 100.0;

    return [
        'total_sent' => $totalSent,
        'pending' => $pendingCount,
        'satisfied' => $satisfiedCount,
        'unsatisfied' => $unsatisfiedCount,
        'total_rated' => $totalRated,
        'satisfaction_rate' => $satisfactionRate
    ];
}

/**
 * جلب سجل طابور وتقييمات العملاء
 */
function get_survey_queue_rows(int $limit = 60, ?string $status = null, ?string $rating = null): array
{
    $sql = "
        SELECT q.*, 
               i.invoice_number, 
               i.total AS invoice_total,
               c.name AS customer_name, 
               loc.name AS location_name
        FROM customer_survey_queue q
        JOIN invoices i ON i.id = q.invoice_id
        LEFT JOIN customers c ON c.id = q.customer_id
        LEFT JOIN locations loc ON loc.id = i.location_id
        WHERE 1=1
    ";
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= " AND q.status = ?";
        $params[] = $status;
    }
    if ($rating !== null && $rating !== '') {
        $sql .= " AND q.rating = ?";
        $params[] = $rating;
    }
    $sql .= " ORDER BY q.id DESC LIMIT ?";
    $params[] = $limit;

    $stmt = pdo()->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * تسجيل تقييم العميل من خلال الرابط التفاعلي
 */
function record_customer_survey_feedback(string $token, string $rating, ?string $notes = null): ?array
{
    $db = pdo();
    $stmt = $db->prepare("
        SELECT q.*, i.invoice_number, loc.name AS location_name, c.name AS customer_name
        FROM customer_survey_queue q
        LEFT JOIN invoices i ON i.id = q.invoice_id
        LEFT JOIN locations loc ON loc.id = i.location_id
        LEFT JOIN customers c ON c.id = COALESCE(q.customer_id, i.customer_id)
        WHERE q.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $survey = $stmt->fetch();
    if (!$survey) return null;

    // إذا كان العميل قد قام بالتقييم مسبقاً، نمنع تغيير التقييم (قفل التقييم)
    if (!empty($survey['rating'])) {
        if ($notes !== null && trim($notes) !== '') {
            $upd = $db->prepare("UPDATE customer_survey_queue SET feedback_notes = ? WHERE token = ?");
            $upd->execute([trim($notes), $token]);
            $survey['feedback_notes'] = trim($notes);
        }
        return $survey;
    }

    $validRating = ($rating === 'satisfied' || $rating === '1') ? 'satisfied' : 'unsatisfied';
    
    $sql = "UPDATE customer_survey_queue SET rating = ?, rated_at = NOW()";
    $params = [$validRating];
    if ($notes !== null && trim($notes) !== '') {
        $sql .= ", feedback_notes = ?";
        $params[] = trim($notes);
    }
    $sql .= " WHERE token = ? AND rating IS NULL";
    $params[] = $token;

    $upd = $db->prepare($sql);
    $upd->execute($params);

    $survey['rating'] = $validRating;
    $survey['rated_at'] = date('Y-m-d H:i:s');
    if ($notes !== null) $survey['feedback_notes'] = trim($notes);
    return $survey;
}

/**
 * تسجيل دين مباشر على العميل دون ارتباط بفاتورة أو منتج
 */
function add_direct_customer_debt(int $customerId, float $amount, ?string $notes = null, ?int $locationId = null, ?int $userId = null): int
{
    if ($customerId <= 0) {
        throw new RuntimeException('العميل غير محدد.');
    }
    if ($amount <= 0) {
        throw new RuntimeException('مبلغ الدين يجب أن يكون أكبر من الصفر.');
    }

    $customer = find_customer($customerId);
    if (!$customer) {
        throw new RuntimeException('العميل غير موجود.');
    }

    $db = pdo();
    $stmt = $db->prepare("
        INSERT INTO customer_debts 
            (customer_id, invoice_id, original_amount, paid_amount, remaining_amount, status, location_id, notes, created_by, created_at)
        VALUES 
            (?, NULL, ?, 0, ?, 'open', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $customerId,
        $amount,
        $amount,
        $locationId ?: null,
        $notes ? trim($notes) : null,
        $userId ?: null,
    ]);

    $debtId = (int)$db->lastInsertId();
    log_audit($userId, 'create', 'customer_debt', $debtId, "تسجيل دين مباشر على العميل {$customer['name']} بقيمة {$amount} ج.م" . ($notes ? " (بيان: {$notes})" : ''));
    return $debtId;
}

/**
 * الحصول على أحدث تحديث للنظام لتنبيه الأجهزة المفتوحة
 */
function get_latest_system_update(): ?array
{
    $db = pdo();
    try {
        $stmt = $db->query("
            SELECT u.*, us.name AS author_name 
            FROM system_updates u 
            LEFT JOIN users us ON us.id = u.created_by 
            WHERE u.is_active = 1 
            ORDER BY u.id DESC 
            LIMIT 1
        ");
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * جلب جميع تحديثات النظام السابقة
 */
function all_system_updates(int $limit = 50): array
{
    $db = pdo();
    try {
        $stmt = $db->prepare("
            SELECT u.*, us.name AS author_name 
            FROM system_updates u 
            LEFT JOIN users us ON us.id = u.created_by 
            ORDER BY u.id DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * إنشاء ونشر تحديث نظام جديد لبثه فورياً لكل الأجهزة المفتوحة
 */
function create_system_update(string $title, string $content, ?string $version = null, string $urgency = 'important', ?int $userId = null): int
{
    $title = trim($title);
    $content = trim($content);
    if ($title === '') {
        throw new RuntimeException('عنوان التحديث مطلوب.');
    }
    if ($content === '') {
        throw new RuntimeException('تفاصيل التحديث مطلوبة.');
    }
    if (!in_array($urgency, ['normal', 'important', 'critical'], true)) {
        $urgency = 'important';
    }

    $db = pdo();
    $stmt = $db->prepare("
        INSERT INTO system_updates (title, version, content, urgency, is_active, created_by, created_at)
        VALUES (?, ?, ?, ?, 1, ?, NOW())
    ");
    $stmt->execute([
        $title,
        $version ? trim($version) : null,
        $content,
        $urgency,
        $userId ?: null,
    ]);
    $updateId = (int)$db->lastInsertId();
    log_audit($userId, 'create', 'system_update', $updateId, "نشر تحديث نظام جديد: {$title}");
    return $updateId;
}

/**
 * حذف إعلان تحديث
 */
function delete_system_update(int $id, ?int $userId = null): void
{
    $db = pdo();
    $stmt = $db->prepare("DELETE FROM system_updates WHERE id = ?");
    $stmt->execute([$id]);
    log_audit($userId, 'delete', 'system_update', $id, "حذف إعلان تحديث النظام #{$id}");
}

/**
 * =========================================================================
 * عروض المنتجات والباكدجات (Offers & Bundles)
 * =========================================================================
 */

function all_offers_list(): array
{
    $db = pdo();
    $sql = "
        SELECT o.*, u.name AS creator_name,
               COUNT(DISTINCT oi.id) AS items_count,
               DATEDIFF(o.end_date, CURDATE()) AS days_left
        FROM offers o
        LEFT JOIN users u ON u.id = o.created_by
        LEFT JOIN offer_items oi ON oi.offer_id = o.id
        GROUP BY o.id
        ORDER BY o.is_active DESC, (CASE WHEN o.end_date >= CURDATE() THEN 0 ELSE 1 END) ASC, o.end_date ASC, o.id DESC
    ";
    return $db->query($sql)->fetchAll();
}

function find_offer(int $id): ?array
{
    $stmt = pdo()->prepare("SELECT o.*, DATEDIFF(o.end_date, CURDATE()) AS days_left FROM offers o WHERE o.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function find_offer_with_items(int $id): ?array
{
    $offer = find_offer($id);
    if (!$offer) {
        return null;
    }
    $db = pdo();
    $stmtItems = $db->prepare("
        SELECT oi.*, 
               p.name AS product_name, p.sale_price AS product_price, p.type AS product_type,
               b.name AS bottle_name, b.sale_price AS bottle_price, bd.size_ml AS bottle_size_ml
        FROM offer_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        LEFT JOIN products b ON b.id = oi.bottle_product_id
        LEFT JOIN product_bottle_details bd ON bd.product_id = b.id
        WHERE oi.offer_id = ?
        ORDER BY oi.id ASC
    ");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    foreach ($items as &$item) {
        if ($item['item_type'] === 'recipe') {
            $stmtComp = $db->prepare("
                SELECT c.*, p.name AS perfume_name, p.sale_price, ppd.quality_grade, ppd.price_per_gram
                FROM offer_item_components c
                JOIN products p ON p.id = c.perfume_product_id
                LEFT JOIN product_perfume_details ppd ON ppd.product_id = p.id
                WHERE c.offer_item_id = ?
                ORDER BY c.id ASC
            ");
            $stmtComp->execute([$item['id']]);
            $item['components'] = $stmtComp->fetchAll();
        } else {
            $item['components'] = [];
        }
    }
    unset($item);

    $offer['items'] = $items;
    return $offer;
}

function active_offers_for_pos(?int $locationId = null): array
{
    $db = pdo();
    $stmt = $db->query("
        SELECT o.*, DATEDIFF(o.end_date, CURDATE()) AS days_left
        FROM offers o
        WHERE o.is_active = 1
          AND o.start_date <= CURDATE()
          AND o.end_date >= CURDATE()
        ORDER BY o.id DESC
    ");
    $offers = $stmt->fetchAll();
    $result = [];
    foreach ($offers as $o) {
        $withItems = find_offer_with_items((int)$o['id']);
        if ($withItems) {
            $result[] = $withItems;
        }
    }
    return $result;
}

function expiring_offers_rows(int $days = 3): array
{
    $stmt = pdo()->prepare("
        SELECT o.*, DATEDIFF(o.end_date, CURDATE()) AS days_left
        FROM offers o
        WHERE o.is_active = 1
          AND o.end_date >= CURDATE()
          AND DATEDIFF(o.end_date, CURDATE()) <= ?
        ORDER BY o.end_date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

function create_offer(array $data, ?int $userId = null): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('اسم العرض مطلوب.');
    }
    $priceBefore = (float)($data['price_before'] ?? 0);
    $priceAfter = (float)($data['price_after'] ?? 0);
    if ($priceAfter < 0) {
        throw new RuntimeException('سعر العرض بعد الخصم يجب أن يكون 0 أو أكثر.');
    }
    $startDate = !empty($data['start_date']) ? (string)$data['start_date'] : date('Y-m-d');
    $endDate = !empty($data['end_date']) ? (string)$data['end_date'] : date('Y-m-d', strtotime('+30 days'));
    if ($endDate < $startDate) {
        throw new RuntimeException('تاريخ نهاية العرض لا يمكن أن يكون قبل تاريخ البدء.');
    }
    $notes = !empty($data['notes']) ? trim((string)$data['notes']) : null;
    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] ? 1 : 0) : 1;

    $db = pdo();
    $barcode = trim((string)($data['barcode'] ?? ''));
    if ($barcode === '') {
        $barcode = generate_unique_ean13($db);
    } else {
        $stmtCheck = $db->prepare('SELECT COUNT(*) FROM offers WHERE barcode = ?');
        $stmtCheck->execute([$barcode]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('الباركود مستخدم بالفعل في عرض آخر.');
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO offers (name, barcode, price_before, price_after, start_date, end_date, is_active, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $barcode, $priceBefore, $priceAfter, $startDate, $endDate, $isActive, $notes, $userId]);
        $offerId = (int)$db->lastInsertId();

        // Process Direct Products items
        if (!empty($data['items']['product_id']) && is_array($data['items']['product_id'])) {
            $stmtItem = $db->prepare("
                INSERT INTO offer_items (offer_id, item_type, product_id, quantity)
                VALUES (?, 'product', ?, ?)
            ");
            foreach ($data['items']['product_id'] as $idx => $prodIdRaw) {
                $prodId = (int)$prodIdRaw;
                $qty = (float)($data['items']['quantity'][$idx] ?? 1);
                if ($prodId > 0 && $qty > 0) {
                    $stmtItem->execute([$offerId, $prodId, $qty]);
                }
            }
        }

        // Process Recipe / Mix items
        if (!empty($data['recipes']) && is_array($data['recipes'])) {
            $stmtRecipeItem = $db->prepare("
                INSERT INTO offer_items (offer_id, item_type, bottle_product_id, recipe_name, quantity)
                VALUES (?, 'recipe', ?, ?, ?)
            ");
            $stmtComp = $db->prepare("
                INSERT INTO offer_item_components (offer_item_id, perfume_product_id, grams)
                VALUES (?, ?, ?)
            ");

            foreach ($data['recipes'] as $rec) {
                $recipeName = trim((string)($rec['name'] ?? 'تركيبة خاصة'));
                $bottleId = !empty($rec['bottle_id']) ? (int)$rec['bottle_id'] : null;
                $qty = max(1.0, (float)($rec['quantity'] ?? 1));

                $stmtRecipeItem->execute([$offerId, $bottleId, $recipeName, $qty]);
                $offerItemId = (int)$db->lastInsertId();

                if (!empty($rec['oils']) && is_array($rec['oils'])) {
                    foreach ($rec['oils'] as $oil) {
                        $perfumeId = (int)($oil['perfume_id'] ?? 0);
                        $grams = (float)($oil['grams'] ?? 0);
                        if ($perfumeId > 0 && $grams > 0) {
                            $stmtComp->execute([$offerItemId, $perfumeId, $grams]);
                        }
                    }
                }
            }
        }

        $db->commit();
        log_audit($userId, 'create', 'offer', $offerId, "إنشاء عرض جديد: {$name} بسعر {$priceAfter} ج.م");
        return $offerId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_offer(int $id, array $data, ?int $userId = null): void
{
    $offer = find_offer($id);
    if (!$offer) {
        throw new RuntimeException('العرض غير موجود.');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('اسم العرض مطلوب.');
    }
    $priceBefore = (float)($data['price_before'] ?? 0);
    $priceAfter = (float)($data['price_after'] ?? 0);
    if ($priceAfter < 0) {
        throw new RuntimeException('سعر العرض بعد الخصم يجب أن يكون 0 أو أكثر.');
    }
    $startDate = !empty($data['start_date']) ? (string)$data['start_date'] : $offer['start_date'];
    $endDate = !empty($data['end_date']) ? (string)$data['end_date'] : $offer['end_date'];
    if ($endDate < $startDate) {
        throw new RuntimeException('تاريخ نهاية العرض لا يمكن أن يكون قبل تاريخ البدء.');
    }
    $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;
    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] ? 1 : 0) : (int)$offer['is_active'];

    $db = pdo();
    $barcode = trim((string)($data['barcode'] ?? ''));
    if ($barcode === '') {
        $barcode = $offer['barcode'] ?: generate_unique_ean13($db);
    } else {
        $stmtCheck = $db->prepare('SELECT COUNT(*) FROM offers WHERE barcode = ? AND id != ?');
        $stmtCheck->execute([$barcode, $id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('الباركود مستخدم بالفعل في عرض آخر.');
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            UPDATE offers
            SET name = ?, barcode = ?, price_before = ?, price_after = ?, start_date = ?, end_date = ?, is_active = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $barcode, $priceBefore, $priceAfter, $startDate, $endDate, $isActive, $notes, $id]);

        // Rebuild items
        $stmtDel = $db->prepare("DELETE FROM offer_items WHERE offer_id = ?");
        $stmtDel->execute([$id]);

        // Process Direct Products items
        if (!empty($data['items']['product_id']) && is_array($data['items']['product_id'])) {
            $stmtItem = $db->prepare("
                INSERT INTO offer_items (offer_id, item_type, product_id, quantity)
                VALUES (?, 'product', ?, ?)
            ");
            foreach ($data['items']['product_id'] as $idx => $prodIdRaw) {
                $prodId = (int)$prodIdRaw;
                $qty = (float)($data['items']['quantity'][$idx] ?? 1);
                if ($prodId > 0 && $qty > 0) {
                    $stmtItem->execute([$id, $prodId, $qty]);
                }
            }
        }

        // Process Recipe / Mix items
        if (!empty($data['recipes']) && is_array($data['recipes'])) {
            $stmtRecipeItem = $db->prepare("
                INSERT INTO offer_items (offer_id, item_type, bottle_product_id, recipe_name, quantity)
                VALUES (?, 'recipe', ?, ?, ?)
            ");
            $stmtComp = $db->prepare("
                INSERT INTO offer_item_components (offer_item_id, perfume_product_id, grams)
                VALUES (?, ?, ?)
            ");

            foreach ($data['recipes'] as $rec) {
                $recipeName = trim((string)($rec['name'] ?? 'تركيبة خاصة'));
                $bottleId = !empty($rec['bottle_id']) ? (int)$rec['bottle_id'] : null;
                $qty = max(1.0, (float)($rec['quantity'] ?? 1));

                $stmtRecipeItem->execute([$id, $bottleId, $recipeName, $qty]);
                $offerItemId = (int)$db->lastInsertId();

                if (!empty($rec['oils']) && is_array($rec['oils'])) {
                    foreach ($rec['oils'] as $oil) {
                        $perfumeId = (int)($oil['perfume_id'] ?? 0);
                        $grams = (float)($oil['grams'] ?? 0);
                        if ($perfumeId > 0 && $grams > 0) {
                            $stmtComp->execute([$offerItemId, $perfumeId, $grams]);
                        }
                    }
                }
            }
        }

        $db->commit();
        log_audit($userId, 'update', 'offer', $id, "تعديل بيانات العرض: {$name}");
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_offer(int $id, ?int $userId = null): void
{
    $offer = find_offer($id);
    if (!$offer) {
        throw new RuntimeException('العرض غير موجود.');
    }
    $db = pdo();
    $stmt = $db->prepare("DELETE FROM offers WHERE id = ?");
    $stmt->execute([$id]);
    log_audit($userId, 'delete', 'offer', $id, "حذف العرض: {$offer['name']}");
}

function toggle_offer_status(int $id, ?int $userId = null): bool
{
    $offer = find_offer($id);
    if (!$offer) {
        throw new RuntimeException('العرض غير موجود.');
    }
    $newStatus = (int)$offer['is_active'] ? 0 : 1;
    $stmt = pdo()->prepare("UPDATE offers SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    $action = $newStatus ? 'تفعيل' : 'تعطيل';
    log_audit($userId, 'update', 'offer', $id, "{$action} العرض: {$offer['name']}");
    return (bool)$newStatus;
}




