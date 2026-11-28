<?php

declare(strict_types=1);

/**
 * Monthly Comprehensive Report Export Engine (Styled OOXML .xlsx + Luxury A4 PDF)
 */

function monthly_report_arabic_month_label(string $month): string
{
    $months = [
        '01' => 'يناير',
        '02' => 'فبراير',
        '03' => 'مارس',
        '04' => 'أبريل',
        '05' => 'مايو',
        '06' => 'يونيو',
        '07' => 'يوليو',
        '08' => 'أغسطس',
        '09' => 'سبتمبر',
        '10' => 'أكتوبر',
        '11' => 'نوفمبر',
        '12' => 'ديسمبر',
    ];
    if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
        $label = $months[$m[2]] ?? $m[2];
        return $label . ' ' . $m[1];
    }
    return $month;
}

function monthly_report_payment_label(?string $method): string
{
    $map = [
        'cash'             => 'كاش (نقداً)',
        'instapay'         => 'إنستا باي (InstaPay)',
        'vodafone_cash'    => 'فودافون كاش',
        'bank_transfer'    => 'تحويل بنكي',
        'salary_deduction' => 'خصم من الراتب',
        'customer_credit'  => 'رصيد عميل',
        'exchange_credit'  => 'رصيد استبدال',
        'exchange'         => 'استبدال',
    ];
    $key = trim((string) ($method ?? ''));
    if ($key === '') {
        return '—';
    }
    if (str_contains($key, ',')) {
        $parts = array_filter(array_map('trim', explode(',', $key)));
        return implode(' + ', array_map(static fn(string $p): string => monthly_report_payment_label($p), $parts));
    }
    return $map[$key] ?? $key;
}

function monthly_report_transfer_status_label(?string $status): string
{
    return match (trim((string) ($status ?? ''))) {
        'received'  => 'تم الاستلام',
        'pending'   => 'قيد الانتظار',
        'cancelled' => 'ملغي',
        default     => (string) ($status ?: '—'),
    };
}

function monthly_report_xml_clean(string $val): string
{
    // Remove illegal XML 1.0 control characters except \t, \n, \r
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
    if ($cleaned === null) {
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $val) ?? '';
    }
    return htmlspecialchars($cleaned, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function collect_monthly_report_data(string $month, ?int $locationId = null): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $db = pdo();
    $startDateOnly = $month . '-01';
    $endDateOnly   = date('Y-m-t', strtotime($startDateOnly));
    $startDateTime = $startDateOnly . ' 00:00:00';
    $endDateTime   = $endDateOnly . ' 23:59:59';

    $locationName = 'كل الفروع والمستودعات';
    if ($locationId !== null && $locationId > 0) {
        $stmtLocName = $db->prepare('SELECT name FROM locations WHERE id = ? LIMIT 1');
        $stmtLocName->execute([$locationId]);
        $foundName = $stmtLocName->fetchColumn();
        if ($foundName) {
            $locationName = (string) $foundName;
        }
    } else {
        $locationId = null;
    }

    // 1. Invoices
    $invSql = "SELECT i.id, i.invoice_number, i.location_id, l.name AS location_name,
                      u.name AS user_name, COALESCE(c.name, 'زبون عابر') AS customer_name,
                      COALESCE(c.phone, '') AS customer_phone,
                      i.subtotal, i.discount_amount, i.total, i.paid_total, i.due_total,
                      i.notes, i.created_at,
                      (SELECT GROUP_CONCAT(DISTINCT p.method SEPARATOR ', ') FROM payments p WHERE p.invoice_id = i.id) AS payment_methods
               FROM invoices i
               JOIN locations l ON l.id = i.location_id
               JOIN users u ON u.id = i.user_id
               LEFT JOIN customers c ON c.id = i.customer_id
               WHERE i.created_at BETWEEN ? AND ?";
    $invParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $invSql .= " AND i.location_id = ?";
        $invParams[] = $locationId;
    }
    $invSql .= " ORDER BY i.created_at DESC, i.id DESC";
    $stmt = $db->prepare($invSql);
    $stmt->execute($invParams);
    $invoices = $stmt->fetchAll();

    // 2. Payments by method
    $paySql = "SELECT p.method,
                      COUNT(*) AS tx_count,
                      COALESCE(SUM(p.amount), 0) AS total,
                      COALESCE(SUM(CASE WHEN p.payment_type = 'invoice_payment' THEN p.amount ELSE 0 END), 0) AS invoice_paid,
                      COALESCE(SUM(CASE WHEN p.payment_type = 'debt_payment' THEN p.amount ELSE 0 END), 0) AS debt_paid
               FROM payments p
               LEFT JOIN invoices i ON i.id = p.invoice_id
               WHERE p.created_at BETWEEN ? AND ?";
    $payParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $paySql .= " AND i.location_id = ?";
        $payParams[] = $locationId;
    }
    $paySql .= " GROUP BY p.method ORDER BY total DESC";
    $stmt = $db->prepare($paySql);
    $stmt->execute($payParams);
    $paymentsByMethod = $stmt->fetchAll();

    // 3. Sales by Location
    $locJoinParams = [$startDateTime, $endDateTime];
    $locSql = "SELECT l.id, l.name, l.type,
                      COUNT(i.id) AS invoices_count,
                      COALESCE(SUM(i.subtotal), 0) AS subtotal,
                      COALESCE(SUM(i.discount_amount), 0) AS discount_amount,
                      COALESCE(SUM(i.total), 0) AS total,
                      COALESCE(SUM(i.paid_total), 0) AS paid_total,
                      COALESCE(SUM(i.due_total), 0) AS due_total
               FROM locations l
               LEFT JOIN invoices i ON i.location_id = l.id AND i.created_at BETWEEN ? AND ?";
    if ($locationId !== null) {
        $locSql .= " WHERE l.id = ?";
        $locJoinParams[] = $locationId;
    } else {
        $locSql .= " WHERE l.is_active = 1";
    }
    $locSql .= " GROUP BY l.id ORDER BY total DESC, l.id ASC";
    $stmt = $db->prepare($locSql);
    $stmt->execute($locJoinParams);
    $salesByLocation = $stmt->fetchAll();

    // 4. Sales by User
    $userSql = "SELECT u.id, u.name, COALESCE(l.name, 'إدارة') AS location_name,
                       COUNT(i.id) AS invoices_count,
                       COALESCE(SUM(i.subtotal), 0) AS subtotal,
                       COALESCE(SUM(i.discount_amount), 0) AS discount_amount,
                       COALESCE(SUM(i.total), 0) AS total,
                       COALESCE(SUM(i.paid_total), 0) AS paid_total
                FROM users u
                JOIN invoices i ON i.user_id = u.id AND i.created_at BETWEEN ? AND ?
                LEFT JOIN locations l ON l.id = i.location_id";
    $userParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $userSql .= " WHERE i.location_id = ?";
        $userParams[] = $locationId;
    }
    $userSql .= " GROUP BY u.id, l.name ORDER BY total DESC";
    $stmt = $db->prepare($userSql);
    $stmt->execute($userParams);
    $salesByUser = $stmt->fetchAll();

    // 5. Expenses + Expenses by Category
    $expSql = "SELECT e.id, e.expense_date, c.name AS category_name,
                      COALESCE(l.name, 'مصروف عام / إدارة') AS location_name,
                      e.amount, COALESCE(e.payment_method, 'cash') AS payment_method,
                      COALESCE(e.notes, '') AS notes, u.name AS user_name, e.created_at
               FROM expenses e
               JOIN expense_categories c ON c.id = e.category_id
               LEFT JOIN locations l ON l.id = e.location_id
               JOIN users u ON u.id = e.created_by
               WHERE e.expense_date BETWEEN ? AND ?";
    $expParams = [$startDateOnly, $endDateOnly];
    if ($locationId !== null) {
        $expSql .= " AND e.location_id = ?";
        $expParams[] = $locationId;
    }
    $expSql .= " ORDER BY e.expense_date DESC, e.id DESC";
    $stmt = $db->prepare($expSql);
    $stmt->execute($expParams);
    $expenses = $stmt->fetchAll();

    $expCatMap = [];
    foreach ($expenses as $ex) {
        $cat = (string) ($ex['category_name'] ?: 'أخرى');
        if (!isset($expCatMap[$cat])) {
            $expCatMap[$cat] = ['category_name' => $cat, 'count' => 0, 'total' => 0.0];
        }
        $expCatMap[$cat]['count']++;
        $expCatMap[$cat]['total'] += (float) $ex['amount'];
    }
    $expensesByCategory = array_values($expCatMap);
    usort($expensesByCategory, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

    // 6. Returns
    $returns = [];
    try {
        $retSql = "SELECT r.return_number, i.invoice_number, l.name AS location_name,
                          COALESCE(c.name, 'زبون عابر') AS customer_name,
                          r.refund_method, r.amount, r.refund_paid, COALESCE(r.reason, '') AS reason,
                          u.name AS user_name, r.created_at
                   FROM return_invoices r
                   JOIN invoices i ON i.id = r.original_invoice_id
                   JOIN locations l ON l.id = i.location_id
                   LEFT JOIN customers c ON c.id = i.customer_id
                   JOIN users u ON u.id = r.created_by
                   WHERE r.created_at BETWEEN ? AND ?";
        $retParams = [$startDateTime, $endDateTime];
        if ($locationId !== null) {
            $retSql .= " AND i.location_id = ?";
            $retParams[] = $locationId;
        }
        $retSql .= " ORDER BY r.created_at DESC, r.id DESC";
        $stmt = $db->prepare($retSql);
        $stmt->execute($retParams);
        $returns = $stmt->fetchAll();
    } catch (Throwable $e) {
        $returns = [];
    }

    // 7. Salaries Paid for Month
    $salaries = [];
    try {
        $salSql = "SELECT s.id, u.name AS user_name, COALESCE(l.name, 'إدارة') AS location_name,
                          s.payroll_month, s.basic_salary, s.commission, s.additions, s.deductions,
                          s.net_payout, s.payment_method, admin.name AS paid_by_name, s.paid_at
                   FROM salary_payments s
                   JOIN users u ON u.id = s.user_id
                   JOIN users admin ON admin.id = s.paid_by
                   LEFT JOIN locations l ON l.id = s.location_id
                   WHERE s.payroll_month = ?";
        $salParams = [$month];
        if ($locationId !== null) {
            $salSql .= " AND s.location_id = ?";
            $salParams[] = $locationId;
        }
        $salSql .= " ORDER BY s.paid_at DESC, s.id DESC";
        $stmt = $db->prepare($salSql);
        $stmt->execute($salParams);
        $salaries = $stmt->fetchAll();
    } catch (Throwable $e) {
        $salaries = [];
    }

    // 8. Top Sold Products & Recipes
    $prodSql = "SELECT il.description, il.line_type,
                       COALESCE(SUM(il.quantity), 0) AS qty_sold,
                       COALESCE(SUM(il.discount_amount), 0) AS discount_total,
                       COALESCE(SUM(il.line_total), 0) AS total
                FROM invoice_lines il
                JOIN invoices i ON i.id = il.invoice_id
                WHERE i.created_at BETWEEN ? AND ?";
    $prodParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $prodSql .= " AND i.location_id = ?";
        $prodParams[] = $locationId;
    }
    $prodSql .= " GROUP BY il.description, il.line_type ORDER BY total DESC";
    $stmt = $db->prepare($prodSql);
    $stmt->execute($prodParams);
    $topProducts = $stmt->fetchAll();

    // 9. Perfume Oil Usage (Grams)
    $useSql = "SELECT p.name,
                      COALESCE(SUM(c.quantity), 0) AS grams,
                      COALESCE(SUM(c.quantity * c.unit_cost), 0) AS est_cost
               FROM invoice_line_components c
               JOIN products p ON p.id = c.component_product_id
               JOIN invoice_lines il ON il.id = c.invoice_line_id
               JOIN invoices i ON i.id = il.invoice_id
               WHERE p.type = 'perfume_gram' AND i.created_at BETWEEN ? AND ?";
    $useParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $useSql .= " AND i.location_id = ?";
        $useParams[] = $locationId;
    }
    $useSql .= " GROUP BY p.id ORDER BY grams DESC";
    $stmt = $db->prepare($useSql);
    $stmt->execute($useParams);
    $perfumeUsage = $stmt->fetchAll();

    // 10. New Customers Registered in Month
    $custSql = "SELECT c.name, COALESCE(c.phone, '') AS phone, c.birthdate,
                       c.source, COALESCE(l.name, 'غير محدد') AS location_name, c.created_at
                FROM customers c
                LEFT JOIN locations l ON l.id = c.location_id
                WHERE c.is_active = 1 AND c.created_at BETWEEN ? AND ?";
    $custParams = [$startDateTime, $endDateTime];
    if ($locationId !== null) {
        $custSql .= " AND c.location_id = ?";
        $custParams[] = $locationId;
    }
    $custSql .= " ORDER BY c.created_at DESC, c.id DESC";
    $stmt = $db->prepare($custSql);
    $stmt->execute($custParams);
    $newCustomers = $stmt->fetchAll();

    // 11. Branch Cash Transfers
    $cashTransfers = [];
    try {
        $trSql = "SELECT t.transfer_number, l.name AS location_name, t.method, t.amount,
                         t.transfer_date, t.status, COALESCE(t.notes, '') AS notes,
                         u.name AS created_by_name, COALESCE(ru.name, '—') AS received_by_name
                  FROM branch_cash_transfers t
                  JOIN locations l ON l.id = t.location_id
                  JOIN users u ON u.id = t.created_by
                  LEFT JOIN users ru ON ru.id = t.received_by
                  WHERE t.transfer_date BETWEEN ? AND ?";
        $trParams = [$startDateOnly, $endDateOnly];
        if ($locationId !== null) {
            $trSql .= " AND t.location_id = ?";
            $trParams[] = $locationId;
        }
        $trSql .= " ORDER BY t.transfer_date DESC, t.id DESC";
        $stmt = $db->prepare($trSql);
        $stmt->execute($trParams);
        $cashTransfers = $stmt->fetchAll();
    } catch (Throwable $e) {
        $cashTransfers = [];
    }

    // 12. Waste Log
    $waste = [];
    try {
        $wSql = "SELECT w.id, p.name AS product_name, p.unit, l.name AS location_name,
                        w.quantity, (w.quantity * COALESCE(p.cost_price, 0)) AS est_cost,
                        COALESCE(w.reason, '') AS reason, u.name AS user_name, w.created_at
                 FROM wasted_products w
                 JOIN locations l ON l.id = w.location_id
                 JOIN products p ON p.id = w.product_id
                 JOIN users u ON u.id = w.created_by
                 WHERE w.created_at BETWEEN ? AND ?";
        $wParams = [$startDateTime, $endDateTime];
        if ($locationId !== null) {
            $wSql .= " AND w.location_id = ?";
            $wParams[] = $locationId;
        }
        $wSql .= " ORDER BY w.created_at DESC, w.id DESC";
        $stmt = $db->prepare($wSql);
        $stmt->execute($wParams);
        $waste = $stmt->fetchAll();
    } catch (Throwable $e) {
        $waste = [];
    }

    // 13. Shift Closures
    $shifts = [];
    try {
        $shSql = "SELECT s.shift_date, l.name AS location_name, u.name AS user_name,
                         s.total_invoices, s.total_cash_sales, s.total_instapay, s.total_vodafone_cash,
                         s.total_returns_cash, s.total_expenses_cash, s.expected_cash, s.actual_cash,
                         s.difference, s.cash_transferred, COALESCE(s.notes, '') AS notes
                  FROM shift_closures s
                  JOIN locations l ON l.id = s.location_id
                  JOIN users u ON u.id = s.user_id
                  WHERE s.shift_date BETWEEN ? AND ?";
        $shParams = [$startDateOnly, $endDateOnly];
        if ($locationId !== null) {
            $shSql .= " AND s.location_id = ?";
            $shParams[] = $locationId;
        }
        $shSql .= " ORDER BY s.shift_date DESC, s.id DESC";
        $stmt = $db->prepare($shSql);
        $stmt->execute($shParams);
        $shifts = $stmt->fetchAll();
    } catch (Throwable $e) {
        $shifts = [];
    }

    // 14. Summary KPIs
    $grossSales = 0.0;
    $totalDiscounts = 0.0;
    $netSales = 0.0;
    $totalPaidOnInvoices = 0.0;
    $totalDue = 0.0;
    foreach ($invoices as $inv) {
        $grossSales += (float) $inv['subtotal'];
        $totalDiscounts += (float) $inv['discount_amount'];
        $netSales += (float) $inv['total'];
        $totalPaidOnInvoices += (float) $inv['paid_total'];
        $totalDue += (float) $inv['due_total'];
    }

    $totalCollected = 0.0;
    $invoicePaymentsTotal = 0.0;
    $debtPaymentsTotal = 0.0;
    foreach ($paymentsByMethod as $pm) {
        $totalCollected += (float) $pm['total'];
        $invoicePaymentsTotal += (float) $pm['invoice_paid'];
        $debtPaymentsTotal += (float) $pm['debt_paid'];
    }

    $totalExpenses = array_sum(array_map(static fn(array $r): float => (float) $r['amount'], $expenses));
    $totalReturns = array_sum(array_map(static fn(array $r): float => (float) $r['amount'], $returns));
    $totalRefundPaid = array_sum(array_map(static fn(array $r): float => (float) $r['refund_paid'], $returns));
    $totalSalaries = array_sum(array_map(static fn(array $r): float => (float) $r['net_payout'], $salaries));
    $totalTransfers = array_sum(array_map(
        static fn(array $r): float => ($r['status'] ?? '') !== 'cancelled' ? (float) $r['amount'] : 0.0,
        $cashTransfers
    ));
    $totalWasteCost = array_sum(array_map(static fn(array $r): float => (float) $r['est_cost'], $waste));

    $invoicesCount = count($invoices);
    $avgInvoice = $invoicesCount > 0 ? ($netSales / $invoicesCount) : 0.0;
    $netCashFlow = $totalCollected - $totalExpenses - $totalRefundPaid - $totalSalaries;

    return [
        'month'               => $month,
        'month_label'         => monthly_report_arabic_month_label($month),
        'start_date'          => $startDateOnly,
        'end_date'            => $endDateOnly,
        'location_id'         => $locationId,
        'location_name'       => $locationName,
        'generated_at'        => date('Y-m-d H:i'),
        'summary'             => [
            'invoices_count'         => $invoicesCount,
            'gross_sales'            => $grossSales,
            'total_discounts'        => $totalDiscounts,
            'net_sales'              => $netSales,
            'total_paid_on_invoices' => $totalPaidOnInvoices,
            'total_due'              => $totalDue,
            'total_collected'        => $totalCollected,
            'invoice_payments_total' => $invoicePaymentsTotal,
            'debt_payments_total'    => $debtPaymentsTotal,
            'avg_invoice'            => $avgInvoice,
            'total_expenses'         => $totalExpenses,
            'expenses_count'         => count($expenses),
            'total_returns'          => $totalReturns,
            'total_refund_paid'      => $totalRefundPaid,
            'returns_count'          => count($returns),
            'total_salaries'         => $totalSalaries,
            'salaries_count'         => count($salaries),
            'total_transfers'        => $totalTransfers,
            'total_waste_cost'       => $totalWasteCost,
            'waste_count'            => count($waste),
            'new_customers_count'    => count($newCustomers),
            'shifts_count'           => count($shifts),
            'net_cash_flow'          => $netCashFlow,
        ],
        'invoices'             => $invoices,
        'payments_by_method'   => $paymentsByMethod,
        'sales_by_location'    => $salesByLocation,
        'sales_by_user'        => $salesByUser,
        'expenses'             => $expenses,
        'expenses_by_category' => $expensesByCategory,
        'returns'              => $returns,
        'salaries'             => $salaries,
        'top_products'         => $topProducts,
        'perfume_usage'        => $perfumeUsage,
        'new_customers'        => $newCustomers,
        'cash_transfers'       => $cashTransfers,
        'waste'                => $waste,
        'shifts'               => $shifts,
    ];
}

/**
 * Lightweight, zero-dependency OOXML (.xlsx) writer with RTL support, custom styles,
 * merged banners, frozen panes, column auto-widths, and live SUM formulas.
 */
final class SimpleStyledXlsxWriter
{
    /** @var array<int, array{name: string, tabColor: string, freezeRow: int, rows: array<int, array<int, array<string, mixed>>>, merges: array<int, string>, colWidths: array<int, float>, rowHeights: array<int, float>}> */
    private array $sheets = [];

    public static function colLetter(int $colIndex): string
    {
        // 1-based column index -> A, B, ..., AA
        $letter = '';
        $n = $colIndex;
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $n = intdiv($n - 1, 26);
        }
        return $letter;
    }

    public function addSheet(string $name, string $tabColorHex = 'C9A84C', int $freezeRow = 0): int
    {
        $cleanName = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $name) ?? 'Sheet';
        if (mb_strlen($cleanName, 'UTF-8') > 31) {
            $cleanName = mb_substr($cleanName, 0, 31, 'UTF-8');
        }
        $this->sheets[] = [
            'name'       => $cleanName,
            'tabColor'   => strtoupper(ltrim($tabColorHex, '#')),
            'freezeRow'  => $freezeRow,
            'rows'       => [],
            'merges'     => [],
            'colWidths'  => [],
            'rowHeights' => [],
        ];
        return count($this->sheets) - 1;
    }

    public function setFreezeRow(int $sheetIdx, int $freezeRow): void
    {
        $this->sheets[$sheetIdx]['freezeRow'] = $freezeRow;
    }

    /**
     * @param array<int, mixed> $cells Each cell can be scalar OR ['v' => ..., 's' => int, 'type' => 'str'|'num', 'formula' => string]
     */
    public function addRow(int $sheetIdx, array $cells, ?float $height = null): int
    {
        $rowNum = count($this->sheets[$sheetIdx]['rows']) + 1;
        $normalized = [];
        foreach (array_values($cells) as $idx => $cell) {
            $colNum = $idx + 1;
            if (is_array($cell)) {
                $val = $cell['v'] ?? '';
                $style = (int) ($cell['s'] ?? 5);
                $formula = isset($cell['formula']) ? (string) $cell['formula'] : null;
                $type = $cell['type'] ?? (($formula !== null || is_int($val) || is_float($val)) ? 'num' : 'str');
            } else {
                $val = $cell;
                $style = (is_int($val) || is_float($val)) ? 7 : 5;
                $formula = null;
                $type = (is_int($val) || is_float($val)) ? 'num' : 'str';
            }
            $normalized[] = [
                'col'     => $colNum,
                'v'       => $val,
                's'       => $style,
                'type'    => $type,
                'formula' => $formula,
            ];

            // Track suggested column width (ignore wide merged title cells handled explicitly)
            $strLen = mb_strlen((string) ($formula !== null ? '123,456.78' : $val), 'UTF-8');
            $prevW = $this->sheets[$sheetIdx]['colWidths'][$colNum] ?? 11.0;
            if ($formula !== null || ($style !== 1 && $style !== 2 && $style !== 3)) {
                $computed = min(46.0, max(12.0, $strLen + 5.0));
                if ($computed > $prevW) {
                    $this->sheets[$sheetIdx]['colWidths'][$colNum] = $computed;
                }
            }
        }
        $this->sheets[$sheetIdx]['rows'][$rowNum] = $normalized;
        if ($height !== null) {
            $this->sheets[$sheetIdx]['rowHeights'][$rowNum] = $height;
        }
        return $rowNum;
    }

    public function mergeRow(int $sheetIdx, int $rowNum, int $startCol, int $endCol): void
    {
        if ($endCol <= $startCol) {
            return;
        }
        $ref = self::colLetter($startCol) . $rowNum . ':' . self::colLetter($endCol) . $rowNum;
        $this->sheets[$sheetIdx]['merges'][] = $ref;
    }

    public function setMinColWidths(int $sheetIdx, array $widthsByOneBasedCol): void
    {
        foreach ($widthsByOneBasedCol as $col => $w) {
            $cur = $this->sheets[$sheetIdx]['colWidths'][(int) $col] ?? 10.0;
            if ((float) $w > $cur) {
                $this->sheets[$sheetIdx]['colWidths'][(int) $col] = (float) $w;
            }
        }
    }

    public function save(string $filepath): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('امتداد ZipArchive غير مفعل في PHP لإنشاء ملف Excel.');
        }
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر فتح ملف Excel المؤقت للكتابة.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());

        foreach ($this->sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $zip->addFromString("xl/worksheets/sheet{$sheetNum}.xml", $this->buildSheetXml($sheet));
        }

        $zip->close();
    }

    private function buildContentTypesXml(): string
    {
        $overrides = [
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
        ];
        foreach ($this->sheets as $idx => $_) {
            $n = $idx + 1;
            $overrides[] = '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . implode('', $overrides)
            . '</Types>';
    }

    private function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbookXml(): string
    {
        $sheetsXml = [];
        foreach ($this->sheets as $idx => $sheet) {
            $n = $idx + 1;
            $name = monthly_report_xml_clean($sheet['name']);
            $sheetsXml[] = '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . implode('', $sheetsXml) . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRelsXml(): string
    {
        $rels = [];
        foreach ($this->sheets as $idx => $_) {
            $n = $idx + 1;
            $rels[] = '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $styleRid = count($this->sheets) + 1;
        $rels[] = '<Relationship Id="rId' . $styleRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', $rels)
            . '</Relationships>';
    }

    private function buildStylesXml(): string
    {
        // Styles mapping:
        // 0: default | 1: title | 2: subtitle | 3: section bar | 4: table header
        // 5: text | 6: text zebra | 7: currency | 8: currency zebra | 9: qty/int | 10: qty/int zebra
        // 11: totals label | 12: totals currency | 13: totals qty | 14: danger currency
        // 15: KPI label | 16: KPI positive | 17: KPI negative
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            .   '<numFmt numFmtId="164" formatCode="#,##0.00"/>'
            .   '<numFmt numFmtId="165" formatCode="#,##0.##"/>'
            . '</numFmts>'
            . '<fonts count="8">'
            .   '<font><sz val="10.5"/><color rgb="FF1F160D"/><name val="Cairo"/></font>' // 0: default
            .   '<font><b/><sz val="15"/><color rgb="FFE8C96A"/><name val="Cairo"/></font>' // 1: title gold
            .   '<font><b/><sz val="10"/><color rgb="FF5C4321"/><name val="Cairo"/></font>' // 2: subtitle brown
            .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Cairo"/></font>' // 3: section white
            .   '<font><b/><sz val="10.5"/><color rgb="FFF5E6C4"/><name val="Cairo"/></font>' // 4: table header cream
            .   '<font><b/><sz val="11"/><color rgb="FF0F5132"/><name val="Cairo"/></font>' // 5: totals green
            .   '<font><b/><sz val="10.5"/><color rgb="FF991B1B"/><name val="Cairo"/></font>' // 6: danger red
            .   '<font><b/><sz val="11"/><color rgb="FF3A2916"/><name val="Cairo"/></font>' // 7: KPI label dark
            . '</fonts>'
            . '<fills count="10">'
            .   '<fill><patternFill patternType="none"/></fill>' // 0 (required)
            .   '<fill><patternFill patternType="gray125"/></fill>' // 1 (required)
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF1C140D"/></patternFill></fill>' // 2: espresso
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF6EFE0"/></patternFill></fill>' // 3: warm cream
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFB88928"/></patternFill></fill>' // 4: royal gold
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF2C2014"/></patternFill></fill>' // 5: dark brown header
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFFAF6EE"/></patternFill></fill>' // 6: zebra cream
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4EA"/></patternFill></fill>' // 7: soft green totals
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFFDECEC"/></patternFill></fill>' // 8: soft red
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF3EAD6"/></patternFill></fill>' // 9: KPI label bg
            . '</fills>'
            . '<borders count="4">'
            .   '<border><left/><right/><top/><bottom/><diagonal/></border>' // 0: none
            .   '<border><left style="thin"><color rgb="FFDFD3BC"/></left><right style="thin"><color rgb="FFDFD3BC"/></right><top style="thin"><color rgb="FFDFD3BC"/></top><bottom style="thin"><color rgb="FFDFD3BC"/></bottom><diagonal/></border>' // 1: cell grid
            .   '<border><left style="thin"><color rgb="FFC9A84C"/></left><right style="thin"><color rgb="FFC9A84C"/></right><top style="thin"><color rgb="FFC9A84C"/></top><bottom style="medium"><color rgb="FFC9A84C"/></bottom><diagonal/></border>' // 2: header gold
            .   '<border><left style="thin"><color rgb="FF198754"/></left><right style="thin"><color rgb="FF198754"/></right><top style="thin"><color rgb="FF198754"/></top><bottom style="double"><color rgb="FF198754"/></bottom><diagonal/></border>' // 3: totals double bottom
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="18">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 0: default
            .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 1: title
            .   '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 2: subtitle
            .   '<xf numFmtId="0" fontId="3" fillId="4" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 3: section bar
            .   '<xf numFmtId="0" fontId="4" fillId="5" borderId="2" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 4: table header
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 5: text normal
            .   '<xf numFmtId="0" fontId="0" fillId="6" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 6: text zebra
            .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 7: currency normal
            .   '<xf numFmtId="164" fontId="0" fillId="6" borderId="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 8: currency zebra
            .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 9: qty/int normal
            .   '<xf numFmtId="165" fontId="0" fillId="6" borderId="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 10: qty/int zebra
            .   '<xf numFmtId="0" fontId="5" fillId="7" borderId="3" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 11: totals label
            .   '<xf numFmtId="164" fontId="5" fillId="7" borderId="3" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 12: totals currency
            .   '<xf numFmtId="165" fontId="5" fillId="7" borderId="3" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 13: totals qty
            .   '<xf numFmtId="164" fontId="6" fillId="8" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 14: danger currency
            .   '<xf numFmtId="0" fontId="7" fillId="9" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 15: KPI label
            .   '<xf numFmtId="164" fontId="5" fillId="7" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 16: KPI positive
            .   '<xf numFmtId="164" fontId="6" fillId="8" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 17: KPI negative
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    private function buildSheetXml(array $sheet): string
    {
        $paneXml = '';
        if ($sheet['freezeRow'] > 0) {
            $topCell = 'A' . ($sheet['freezeRow'] + 1);
            $paneXml = '<pane ySplit="' . $sheet['freezeRow'] . '" topLeftCell="' . $topCell . '" activePane="bottomLeft" state="frozen"/>';
        }

        $colsXml = '';
        if (!empty($sheet['colWidths'])) {
            ksort($sheet['colWidths']);
            $colTags = [];
            foreach ($sheet['colWidths'] as $colNum => $w) {
                $colTags[] = '<col min="' . $colNum . '" max="' . $colNum . '" width="' . number_format((float) $w, 2, '.', '') . '" customWidth="1"/>';
            }
            $colsXml = '<cols>' . implode('', $colTags) . '</cols>';
        }

        $rowsXml = [];
        foreach ($sheet['rows'] as $rowNum => $cells) {
            $htAttr = isset($sheet['rowHeights'][$rowNum])
                ? ' ht="' . number_format((float) $sheet['rowHeights'][$rowNum], 1, '.', '') . '" customHeight="1"'
                : ' ht="22" customHeight="1"';
            $cellsXml = [];
            foreach ($cells as $c) {
                $ref = self::colLetter($c['col']) . $rowNum;
                $s = (int) $c['s'];
                if ($c['formula'] !== null) {
                    $fClean = monthly_report_xml_clean($c['formula']);
                    $cellsXml[] = '<c r="' . $ref . '" s="' . $s . '"><f>' . $fClean . '</f></c>';
                } elseif ($c['type'] === 'num') {
                    $numVal = is_numeric($c['v']) ? (string) (0 + $c['v']) : '0';
                    $cellsXml[] = '<c r="' . $ref . '" s="' . $s . '"><v>' . $numVal . '</v></c>';
                } else {
                    $txt = monthly_report_xml_clean((string) $c['v']);
                    $cellsXml[] = '<c r="' . $ref . '" t="inlineStr" s="' . $s . '"><is><t>' . $txt . '</t></is></c>';
                }
            }
            $rowsXml[] = '<row r="' . $rowNum . '"' . $htAttr . '>' . implode('', $cellsXml) . '</row>';
        }

        $mergesXml = '';
        if (!empty($sheet['merges'])) {
            $mTags = array_map(static fn(string $ref): string => '<mergeCell ref="' . $ref . '"/>', $sheet['merges']);
            $mergesXml = '<mergeCells count="' . count($mTags) . '">' . implode('', $mTags) . '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><tabColor rgb="FF' . $sheet['tabColor'] . '"/></sheetPr>'
            . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1">' . $paneXml . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="22"/>'
            . $colsXml
            . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
            . $mergesXml
            . '<pageSetup orientation="landscape" paperSize="9"/>'
            . '</worksheet>';
    }
}

function xlsx_add_banner(SimpleStyledXlsxWriter $writer, int $s, string $title, string $subtitle, int $colSpan): void
{
    $r1 = $writer->addRow($s, [['v' => $title, 's' => 1, 'type' => 'str']], 34);
    $writer->mergeRow($s, $r1, 1, $colSpan);
    $r2 = $writer->addRow($s, [['v' => $subtitle, 's' => 2, 'type' => 'str']], 22);
    $writer->mergeRow($s, $r2, 1, $colSpan);
    $writer->addRow($s, [['v' => '', 's' => 0, 'type' => 'str']], 8);
}

function xlsx_sum_cell(int $startRow, int $endRow, int $colNum, int $styleId): array
{
    if ($endRow < $startRow) {
        return ['v' => 0.0, 's' => $styleId, 'type' => 'num'];
    }
    $col = SimpleStyledXlsxWriter::colLetter($colNum);
    return ['formula' => "SUM({$col}{$startRow}:{$col}{$endRow})", 's' => $styleId, 'type' => 'num'];
}

function build_monthly_report_xlsx_file(array $data, string $targetPath): void
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'حمزة للعطور';
    $subtitle = "الفترة: {$data['month_label']} ({$data['start_date']} إلى {$data['end_date']})  |  النطاق: {$data['location_name']}  |  تاريخ الإصدار: {$data['generated_at']}";
    $sum      = $data['summary'];

    $w = new SimpleStyledXlsxWriter();

    // =========================================================================
    // Sheet 1: الملخص التنفيذي
    // =========================================================================
    $s1 = $w->addSheet('الملخص التنفيذي', 'C9A84C', 0);
    xlsx_add_banner($w, $s1, "{$appName} — التقرير المالي والتشغيلي الشامل ({$data['month_label']})", $subtitle, 6);

    $rSec1 = $w->addRow($s1, [['v' => '📌 المؤشرات المالية والتشغيلية الرئيسية للشهر', 's' => 3, 'type' => 'str']], 26);
    $w->mergeRow($s1, $rSec1, 1, 6);
    $w->addRow($s1, [
        ['v' => 'البند المالي / التشغيلي', 's' => 4, 'type' => 'str'],
        ['v' => 'القيمة (ج.م / عدد)', 's' => 4, 'type' => 'str'],
        ['v' => 'البند المالي / التشغيلي', 's' => 4, 'type' => 'str'],
        ['v' => 'القيمة (ج.م / عدد)', 's' => 4, 'type' => 'str'],
        ['v' => 'ملاحظات وتفاصيل', 's' => 4, 'type' => 'str'],
        ['v' => 'الحالة', 's' => 4, 'type' => 'str'],
    ], 24);

    $kpiPairs = [
        ['إجمالي المبيعات قبل الخصم', $sum['gross_sales'], 7, 'عدد الفواتير الصادرة', (int) $sum['invoices_count'], 9, 'شاملة جميع الأصناف والتركيبات', 'مبيعات'],
        ['إجمالي الخصومات الممنوحة', $sum['total_discounts'], 14, 'متوسط قيمة الفاتورة', $sum['avg_invoice'], 7, 'إجمالي خصومات الفواتير', 'خصومات'],
        ['صافي المبيعات المفوترة', $sum['net_sales'], 16, 'المدفوع وقت البيع', $sum['total_paid_on_invoices'], 7, 'قيمة الفواتير بعد الخصم', 'إيراد'],
        ['الديون المتبقية (الآجل)', $sum['total_due'], 14, 'تحصيلات الديون الآجلة', $sum['debt_payments_total'], 7, 'المتبقي غير المحصل وقت البيع', 'ذمم'],
        ['إجمالي التحصيل النقدي والإلكتروني', $sum['total_collected'], 16, 'سداد فواتير + سداد ديون', $sum['invoice_payments_total'] + $sum['debt_payments_total'], 7, 'التدفق النقدي الداخل الفعلي', 'داخل'],
        ['إجمالي المصاريف التشغيلية', $sum['total_expenses'], 17, 'عدد بنود المصاريف', (int) $sum['expenses_count'], 9, 'مصاريف الفروع والإدارة المسجلة', 'منصرف'],
        ['إجمالي المرتجعات المسجلة', $sum['total_returns'], 14, 'المسترد المدفوع للمرتجع', $sum['total_refund_paid'], 17, 'عدد عمليات المرتجع: ' . $sum['returns_count'], 'مرتجع'],
        ['إجمالي الرواتب المنصرفة (HR)', $sum['total_salaries'], 17, 'عدد الموظفين المصروف لهم', (int) $sum['salaries_count'], 9, 'رواتب وعمولات مسير ' . $data['month'], 'رواتب'],
        ['صافي التدفق النقدي الشهري', $sum['net_cash_flow'], $sum['net_cash_flow'] >= 0 ? 16 : 17, 'تكلفة الهالك المقدر', $sum['total_waste_cost'], 14, 'التحصيلات ناقص المصاريف والمسترد والرواتب', $sum['net_cash_flow'] >= 0 ? 'فائض' : 'عجز'],
        ['إجمالي تحويلات الكاش للمدير', $sum['total_transfers'], 7, 'العملاء الجدد / الشيفتات', (int) $sum['new_customers_count'], 9, 'الشيفتات المغلقة: ' . $sum['shifts_count'], 'تشغيل'],
    ];
    foreach ($kpiPairs as $kp) {
        $w->addRow($s1, [
            ['v' => $kp[0], 's' => 15, 'type' => 'str'],
            ['v' => $kp[1], 's' => $kp[2], 'type' => 'num'],
            ['v' => $kp[3], 's' => 15, 'type' => 'str'],
            ['v' => $kp[4], 's' => $kp[5], 'type' => 'num'],
            ['v' => $kp[6], 's' => 6, 'type' => 'str'],
            ['v' => $kp[7], 's' => 5, 'type' => 'str'],
        ], 23);
    }

    // Sales by Location section
    $w->addRow($s1, [['v' => '', 's' => 0, 'type' => 'str']], 10);
    $rSec2 = $w->addRow($s1, [['v' => '🏬 ملخص المبيعات حسب الفرع / الموقع', 's' => 3, 'type' => 'str']], 25);
    $w->mergeRow($s1, $rSec2, 1, 6);
    $w->addRow($s1, [
        ['v' => 'الفرع / الموقع', 's' => 4, 'type' => 'str'],
        ['v' => 'عدد الفواتير', 's' => 4, 'type' => 'str'],
        ['v' => 'قبل الخصم', 's' => 4, 'type' => 'str'],
        ['v' => 'الخصومات', 's' => 4, 'type' => 'str'],
        ['v' => 'صافي المبيعات', 's' => 4, 'type' => 'str'],
        ['v' => 'المتبقي (آجل)', 's' => 4, 'type' => 'str'],
    ], 24);
    $locStart = count($data['sales_by_location']) > 0 ? ($w->addRow($s1, ['v' => 'tmp']) && false) : 0;
    // Remove dummy logic — compute exact row numbers cleanly
    unset($locStart);
    $nextRowLoc = 18; // rows 1..17 used above -> let's track dynamically by peeking next row
    $locStartRow = null;
    $locEndRow   = null;
    foreach ($data['sales_by_location'] as $i => $loc) {
        $z = ($i % 2 === 1);
        $rn = $w->addRow($s1, [
            ['v' => $loc['name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (int) $loc['invoices_count'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $loc['subtotal'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $loc['discount_amount'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $loc['total'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $loc['due_total'], 's' => $z ? 8 : 7, 'type' => 'num'],
        ], 22);
        if ($locStartRow === null) $locStartRow = $rn;
        $locEndRow = $rn;
    }
    if ($locStartRow !== null) {
        $w->addRow($s1, [
            ['v' => 'الإجمالي الكلي للفروع', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($locStartRow, $locEndRow, 2, 13),
            xlsx_sum_cell($locStartRow, $locEndRow, 3, 12),
            xlsx_sum_cell($locStartRow, $locEndRow, 4, 12),
            xlsx_sum_cell($locStartRow, $locEndRow, 5, 12),
            xlsx_sum_cell($locStartRow, $locEndRow, 6, 12),
        ], 24);
    }

    // Payments by method section
    $w->addRow($s1, [['v' => '', 's' => 0, 'type' => 'str']], 10);
    $rSec3 = $w->addRow($s1, [['v' => '💳 توزيع التحصيلات حسب طريقة الدفع', 's' => 3, 'type' => 'str']], 25);
    $w->mergeRow($s1, $rSec3, 1, 6);
    $w->addRow($s1, [
        ['v' => 'طريقة الدفع', 's' => 4, 'type' => 'str'],
        ['v' => 'عدد العمليات', 's' => 4, 'type' => 'str'],
        ['v' => 'تحصيل فواتير', 's' => 4, 'type' => 'str'],
        ['v' => 'سداد ديون', 's' => 4, 'type' => 'str'],
        ['v' => 'الإجمالي المحصل', 's' => 4, 'type' => 'str'],
        ['v' => 'النسبة %', 's' => 4, 'type' => 'str'],
    ], 24);
    $payStartRow = null;
    $payEndRow   = null;
    foreach ($data['payments_by_method'] as $i => $pm) {
        $z = ($i % 2 === 1);
        $pct = $sum['total_collected'] > 0 ? round(((float) $pm['total'] / $sum['total_collected']) * 100, 1) : 0.0;
        $rn = $w->addRow($s1, [
            ['v' => monthly_report_payment_label($pm['method']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (int) $pm['tx_count'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $pm['invoice_paid'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $pm['debt_paid'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $pm['total'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => $pct . '%', 's' => $z ? 10 : 9, 'type' => 'str'],
        ], 22);
        if ($payStartRow === null) $payStartRow = $rn;
        $payEndRow = $rn;
    }
    if ($payStartRow !== null) {
        $w->addRow($s1, [
            ['v' => 'إجمالي التحصيلات', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($payStartRow, $payEndRow, 2, 13),
            xlsx_sum_cell($payStartRow, $payEndRow, 3, 12),
            xlsx_sum_cell($payStartRow, $payEndRow, 4, 12),
            xlsx_sum_cell($payStartRow, $payEndRow, 5, 12),
            ['v' => '100%', 's' => 13, 'type' => 'str'],
        ], 24);
    }

    // Expenses by Category summary
    $w->addRow($s1, [['v' => '', 's' => 0, 'type' => 'str']], 10);
    $rSec4 = $w->addRow($s1, [['v' => '💸 ملخص المصاريف حسب البند', 's' => 3, 'type' => 'str']], 25);
    $w->mergeRow($s1, $rSec4, 1, 6);
    $w->addRow($s1, [
        ['v' => 'بند المصروف', 's' => 4, 'type' => 'str'],
        ['v' => 'عدد البنود', 's' => 4, 'type' => 'str'],
        ['v' => 'إجمالي المنصرف', 's' => 4, 'type' => 'str'],
        ['v' => 'النسبة من المصاريف %', 's' => 4, 'type' => 'str'],
        ['v' => 'ملاحظة', 's' => 4, 'type' => 'str'],
        ['v' => 'الشهر', 's' => 4, 'type' => 'str'],
    ], 24);
    $ecStartRow = null;
    $ecEndRow   = null;
    foreach ($data['expenses_by_category'] as $i => $ec) {
        $z = ($i % 2 === 1);
        $pct = $sum['total_expenses'] > 0 ? round(((float) $ec['total'] / $sum['total_expenses']) * 100, 1) : 0.0;
        $rn = $w->addRow($s1, [
            ['v' => $ec['category_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (int) $ec['count'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $ec['total'], 's' => 14, 'type' => 'num'],
            ['v' => $pct . '%', 's' => $z ? 10 : 9, 'type' => 'str'],
            ['v' => 'تفاصيل المصروف في شيت المصاريف', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $data['month_label'], 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 22);
        if ($ecStartRow === null) $ecStartRow = $rn;
        $ecEndRow = $rn;
    }
    if ($ecStartRow !== null) {
        $w->addRow($s1, [
            ['v' => 'إجمالي المصاريف', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($ecStartRow, $ecEndRow, 2, 13),
            xlsx_sum_cell($ecStartRow, $ecEndRow, 3, 12),
            ['v' => '100%', 's' => 13, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 24);
    }
    $w->setMinColWidths($s1, [1 => 30, 2 => 18, 3 => 28, 4 => 18, 5 => 32, 6 => 16]);

    // =========================================================================
    // Sheet 2: المبيعات والفواتير
    // =========================================================================
    $s2 = $w->addSheet('المبيعات والفواتير', '10B981', 4);
    xlsx_add_banner($w, $s2, "سجل فواتير المبيعات — {$data['month_label']}", $subtitle, 12);
    $w->addRow($s2, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'رقم الفاتورة', 's' => 4, 'type' => 'str'],
        ['v' => 'التاريخ والوقت', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'الكاشير', 's' => 4, 'type' => 'str'],
        ['v' => 'العميل', 's' => 4, 'type' => 'str'],
        ['v' => 'هاتف العميل', 's' => 4, 'type' => 'str'],
        ['v' => 'طرق الدفع', 's' => 4, 'type' => 'str'],
        ['v' => 'قبل الخصم', 's' => 4, 'type' => 'str'],
        ['v' => 'الخصم', 's' => 4, 'type' => 'str'],
        ['v' => 'الصافي', 's' => 4, 'type' => 'str'],
        ['v' => 'المتبقي (دين)', 's' => 4, 'type' => 'str'],
    ], 25);
    $invStart = null;
    $invEnd   = null;
    foreach ($data['invoices'] as $idx => $inv) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s2, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $inv['invoice_number'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $inv['created_at'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $inv['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $inv['user_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $inv['customer_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $inv['customer_phone'] ?: '—', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => monthly_report_payment_label($inv['payment_methods'] ?? ''), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $inv['subtotal'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $inv['discount_amount'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $inv['total'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $inv['due_total'], 's' => (float) $inv['due_total'] > 0 ? 14 : ($z ? 8 : 7), 'type' => 'num'],
        ], 21);
        if ($invStart === null) $invStart = $rn;
        $invEnd = $rn;
    }
    if ($invStart !== null) {
        $w->addRow($s2, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['invoices']) . ' فاتورة', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($invStart, $invEnd, 9, 12),
            xlsx_sum_cell($invStart, $invEnd, 10, 12),
            xlsx_sum_cell($invStart, $invEnd, 11, 12),
            xlsx_sum_cell($invStart, $invEnd, 12, 12),
        ], 25);
    }
    $w->setMinColWidths($s2, [1 => 8, 2 => 18, 3 => 20, 4 => 16, 5 => 18, 6 => 22, 7 => 16, 8 => 24, 9 => 15, 10 => 13, 11 => 15, 12 => 15]);

    // =========================================================================
    // Sheet 3: المصاريف
    // =========================================================================
    $s3 = $w->addSheet('المصاريف', 'DC2626', 4);
    xlsx_add_banner($w, $s3, "سجل المصاريف التشغيلية — {$data['month_label']}", $subtitle, 8);
    $w->addRow($s3, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'تاريخ المصروف', 's' => 4, 'type' => 'str'],
        ['v' => 'البند / الفئة', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع / الموقع', 's' => 4, 'type' => 'str'],
        ['v' => 'طريقة الدفع', 's' => 4, 'type' => 'str'],
        ['v' => 'المبلغ (ج.م)', 's' => 4, 'type' => 'str'],
        ['v' => 'المسؤول', 's' => 4, 'type' => 'str'],
        ['v' => 'البيان / الملاحظات', 's' => 4, 'type' => 'str'],
    ], 25);
    $expStart = null;
    $expEnd   = null;
    foreach ($data['expenses'] as $idx => $ex) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s3, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $ex['expense_date'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ex['category_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ex['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => monthly_report_payment_label($ex['payment_method']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $ex['amount'], 's' => 14, 'type' => 'num'],
            ['v' => $ex['user_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ex['notes'] ?: '—', 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 22);
        if ($expStart === null) $expStart = $rn;
        $expEnd = $rn;
    }
    if ($expStart !== null) {
        $w->addRow($s3, [
            ['v' => 'إجمالي المصاريف', 's' => 11, 'type' => 'str'],
            ['v' => count($data['expenses']) . ' بند', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($expStart, $expEnd, 6, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 25);
    }
    $w->setMinColWidths($s3, [1 => 8, 2 => 15, 3 => 22, 4 => 20, 5 => 20, 6 => 16, 7 => 18, 8 => 36]);

    // =========================================================================
    // Sheet 4: المرتجعات
    // =========================================================================
    $s4 = $w->addSheet('المرتجعات', 'F59E0B', 4);
    xlsx_add_banner($w, $s4, "سجل المرتجعات والاستبدالات — {$data['month_label']}", $subtitle, 9);
    $w->addRow($s4, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'رقم المرتجع', 's' => 4, 'type' => 'str'],
        ['v' => 'رقم الفاتورة الأصلية', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'العميل', 's' => 4, 'type' => 'str'],
        ['v' => 'طريقة الاسترداد', 's' => 4, 'type' => 'str'],
        ['v' => 'قيمة المرتجع', 's' => 4, 'type' => 'str'],
        ['v' => 'المبلغ المدفوع فعلياً', 's' => 4, 'type' => 'str'],
        ['v' => 'السبب / الموظف / التاريخ', 's' => 4, 'type' => 'str'],
    ], 25);
    $retStart = null;
    $retEnd   = null;
    foreach ($data['returns'] as $idx => $ret) {
        $z = ($idx % 2 === 1);
        $meta = trim(($ret['reason'] ? $ret['reason'] . ' — ' : '') . $ret['user_name'] . ' (' . $ret['created_at'] . ')');
        $rn = $w->addRow($s4, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $ret['return_number'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ret['invoice_number'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ret['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ret['customer_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => monthly_report_payment_label($ret['refund_method']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $ret['amount'], 's' => 14, 'type' => 'num'],
            ['v' => (float) $ret['refund_paid'], 's' => 14, 'type' => 'num'],
            ['v' => $meta, 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 22);
        if ($retStart === null) $retStart = $rn;
        $retEnd = $rn;
    }
    if ($retStart !== null) {
        $w->addRow($s4, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['returns']) . ' مرتجع', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($retStart, $retEnd, 7, 12),
            xlsx_sum_cell($retStart, $retEnd, 8, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 25);
    }
    $w->setMinColWidths($s4, [1 => 8, 2 => 22, 3 => 20, 4 => 16, 5 => 20, 6 => 18, 7 => 16, 8 => 18, 9 => 38]);

    // =========================================================================
    // Sheet 5: الرواتب والمستحقات
    // =========================================================================
    $s5 = $w->addSheet('الرواتب والمستحقات', '4F46E5', 4);
    xlsx_add_banner($w, $s5, "مسير الرواتب والعمولات المنصرفة — {$data['month_label']}", $subtitle, 10);
    $w->addRow($s5, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'الموظف', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'الأساسي', 's' => 4, 'type' => 'str'],
        ['v' => 'العمولة', 's' => 4, 'type' => 'str'],
        ['v' => 'الإضافات', 's' => 4, 'type' => 'str'],
        ['v' => 'الخصومات', 's' => 4, 'type' => 'str'],
        ['v' => 'الصافي المنصرف', 's' => 4, 'type' => 'str'],
        ['v' => 'طريقة الصرف', 's' => 4, 'type' => 'str'],
        ['v' => 'تاريخ الصرف والمسؤول', 's' => 4, 'type' => 'str'],
    ], 25);
    $salStart = null;
    $salEnd   = null;
    foreach ($data['salaries'] as $idx => $sal) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s5, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $sal['user_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $sal['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $sal['basic_salary'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $sal['commission'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $sal['additions'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $sal['deductions'], 's' => 14, 'type' => 'num'],
            ['v' => (float) $sal['net_payout'], 's' => 16, 'type' => 'num'],
            ['v' => monthly_report_payment_label($sal['payment_method']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $sal['paid_at'] . ' — ' . $sal['paid_by_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 22);
        if ($salStart === null) $salStart = $rn;
        $salEnd = $rn;
    }
    if ($salStart !== null) {
        $w->addRow($s5, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['salaries']) . ' موظف', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($salStart, $salEnd, 4, 12),
            xlsx_sum_cell($salStart, $salEnd, 5, 12),
            xlsx_sum_cell($salStart, $salEnd, 6, 12),
            xlsx_sum_cell($salStart, $salEnd, 7, 12),
            xlsx_sum_cell($salStart, $salEnd, 8, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 25);
    }
    $w->setMinColWidths($s5, [1 => 8, 2 => 22, 3 => 16, 4 => 14, 5 => 14, 6 => 14, 7 => 14, 8 => 16, 9 => 18, 10 => 28]);

    // =========================================================================
    // Sheet 6: المنتجات والعطور
    // =========================================================================
    $s6 = $w->addSheet('المنتجات والعطور', '0D9488', 0);
    xlsx_add_banner($w, $s6, "حركة المنتجات المباعة واستهلاك الزيوت العطرية — {$data['month_label']}", $subtitle, 5);

    $rP1 = $w->addRow($s6, [['v' => '🛍️ الأصناف والتركيبات المباعة خلال الشهر', 's' => 3, 'type' => 'str']], 25);
    $w->mergeRow($s6, $rP1, 1, 5);
    $w->addRow($s6, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'الصنف / التركيبة', 's' => 4, 'type' => 'str'],
        ['v' => 'النوع', 's' => 4, 'type' => 'str'],
        ['v' => 'الكمية المباعة', 's' => 4, 'type' => 'str'],
        ['v' => 'إجمالي المبيعات (ج.م)', 's' => 4, 'type' => 'str'],
    ], 24);
    $lineTypeLabels = ['product' => 'منتج جاهز', 'custom_recipe' => 'تركيبة فورية', 'saved_recipe' => 'تركيبة جاهزة', 'offer' => 'عرض / باكدج'];
    $pStart = null;
    $pEnd   = null;
    foreach ($data['top_products'] as $idx => $tp) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s6, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $tp['description'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $lineTypeLabels[$tp['line_type']] ?? $tp['line_type'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $tp['qty_sold'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $tp['total'], 's' => $z ? 8 : 7, 'type' => 'num'],
        ], 21);
        if ($pStart === null) $pStart = $rn;
        $pEnd = $rn;
    }
    if ($pStart !== null) {
        $w->addRow($s6, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['top_products']) . ' صنف', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($pStart, $pEnd, 4, 13),
            xlsx_sum_cell($pStart, $pEnd, 5, 12),
        ], 24);
    }

    $w->addRow($s6, [['v' => '', 's' => 0, 'type' => 'str']], 12);
    $rP2 = $w->addRow($s6, [['v' => '🧴 استهلاك الزيوت العطرية بالجرام', 's' => 3, 'type' => 'str']], 25);
    $w->mergeRow($s6, $rP2, 1, 5);
    $w->addRow($s6, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'اسم الزيت العطري', 's' => 4, 'type' => 'str'],
        ['v' => 'الوحدة', 's' => 4, 'type' => 'str'],
        ['v' => 'الكمية المستهلكة (جم)', 's' => 4, 'type' => 'str'],
        ['v' => 'التكلفة التقديرية (ج.م)', 's' => 4, 'type' => 'str'],
    ], 24);
    $uStart = null;
    $uEnd   = null;
    foreach ($data['perfume_usage'] as $idx => $pu) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s6, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $pu['name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => 'جرام', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $pu['grams'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $pu['est_cost'], 's' => $z ? 8 : 7, 'type' => 'num'],
        ], 21);
        if ($uStart === null) $uStart = $rn;
        $uEnd = $rn;
    }
    if ($uStart !== null) {
        $w->addRow($s6, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['perfume_usage']) . ' نوع زيت', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($uStart, $uEnd, 4, 13),
            xlsx_sum_cell($uStart, $uEnd, 5, 12),
        ], 24);
    }
    $w->setMinColWidths($s6, [1 => 8, 2 => 44, 3 => 16, 4 => 20, 5 => 20]);

    // =========================================================================
    // Sheet 7: أداء الموظفين
    // =========================================================================
    $s7 = $w->addSheet('أداء الموظفين', '2563EB', 4);
    xlsx_add_banner($w, $s7, "تقرير أداء المبيعات حسب الموظف — {$data['month_label']}", $subtitle, 8);
    $w->addRow($s7, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'اسم الموظف', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'عدد الفواتير', 's' => 4, 'type' => 'str'],
        ['v' => 'قبل الخصم', 's' => 4, 'type' => 'str'],
        ['v' => 'الخصومات', 's' => 4, 'type' => 'str'],
        ['v' => 'صافي المبيعات', 's' => 4, 'type' => 'str'],
        ['v' => 'متوسط الفاتورة', 's' => 4, 'type' => 'str'],
    ], 25);
    $stStart = null;
    $stEnd   = null;
    foreach ($data['sales_by_user'] as $idx => $su) {
        $z = ($idx % 2 === 1);
        $cnt = (int) $su['invoices_count'];
        $avg = $cnt > 0 ? ((float) $su['total'] / $cnt) : 0.0;
        $rn = $w->addRow($s7, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $su['name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $su['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $cnt, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => (float) $su['subtotal'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $su['discount_amount'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $su['total'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => $avg, 's' => $z ? 8 : 7, 'type' => 'num'],
        ], 22);
        if ($stStart === null) $stStart = $rn;
        $stEnd = $rn;
    }
    if ($stStart !== null) {
        $w->addRow($s7, [
            ['v' => 'الإجمالي', 's' => 11, 'type' => 'str'],
            ['v' => count($data['sales_by_user']) . ' موظف', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($stStart, $stEnd, 4, 13),
            xlsx_sum_cell($stStart, $stEnd, 5, 12),
            xlsx_sum_cell($stStart, $stEnd, 6, 12),
            xlsx_sum_cell($stStart, $stEnd, 7, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 25);
    }
    $w->setMinColWidths($s7, [1 => 8, 2 => 24, 3 => 18, 4 => 14, 5 => 16, 6 => 14, 7 => 16, 8 => 16]);

    // =========================================================================
    // Sheet 8: العملاء الجدد
    // =========================================================================
    $s8 = $w->addSheet('العملاء الجدد', '7C3AED', 4);
    xlsx_add_banner($w, $s8, "سجل العملاء الجدد المسجلين — {$data['month_label']}", $subtitle, 7);
    $w->addRow($s8, [
        ['v' => '#', 's' => 4, 'type' => 'str'],
        ['v' => 'اسم العميل', 's' => 4, 'type' => 'str'],
        ['v' => 'رقم الهاتف', 's' => 4, 'type' => 'str'],
        ['v' => 'تاريخ الميلاد', 's' => 4, 'type' => 'str'],
        ['v' => 'المصدر', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'تاريخ التسجيل', 's' => 4, 'type' => 'str'],
    ], 25);
    foreach ($data['new_customers'] as $idx => $nc) {
        $z = ($idx % 2 === 1);
        $w->addRow($s8, [
            ['v' => $idx + 1, 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $nc['name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $nc['phone'] ?: '—', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $nc['birthdate'] ?: '—', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => ($nc['source'] ?? '') === 'online' ? 'أونلاين' : 'أوفلاين (فرع)', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $nc['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $nc['created_at'], 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 21);
    }
    $w->setMinColWidths($s8, [1 => 8, 2 => 26, 3 => 16, 4 => 14, 5 => 16, 6 => 18, 7 => 20]);

    // =========================================================================
    // Sheet 9: التحويلات والهالك والشيفتات
    // =========================================================================
    $s9 = $w->addSheet('التحويلات والهالك والشيفتات', '475569', 0);
    xlsx_add_banner($w, $s9, "التحويلات النقدية، الهالك، وإغلاق الشيفتات — {$data['month_label']}", $subtitle, 7);

    // 9A Transfers
    $rT = $w->addRow($s9, [['v' => '🏦 توريدات وتحويلات الكاش للمدير', 's' => 3, 'type' => 'str']], 24);
    $w->mergeRow($s9, $rT, 1, 7);
    $w->addRow($s9, [
        ['v' => 'رقم التحويل', 's' => 4, 'type' => 'str'],
        ['v' => 'التاريخ', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'الطريقة', 's' => 4, 'type' => 'str'],
        ['v' => 'المبلغ', 's' => 4, 'type' => 'str'],
        ['v' => 'الحالة', 's' => 4, 'type' => 'str'],
        ['v' => 'المرسل / المستلم', 's' => 4, 'type' => 'str'],
    ], 24);
    $trStart = null;
    $trEnd   = null;
    foreach ($data['cash_transfers'] as $idx => $tr) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s9, [
            ['v' => $tr['transfer_number'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $tr['transfer_date'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $tr['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => monthly_report_payment_label($tr['method']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $tr['amount'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => monthly_report_transfer_status_label($tr['status']), 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $tr['created_by_name'] . ' ← ' . $tr['received_by_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 21);
        if ($trStart === null) $trStart = $rn;
        $trEnd = $rn;
    }
    if ($trStart !== null) {
        $w->addRow($s9, [
            ['v' => 'إجمالي التحويلات', 's' => 11, 'type' => 'str'],
            ['v' => count($data['cash_transfers']) . ' تحويل', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($trStart, $trEnd, 5, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 24);
    }

    // 9B Waste
    $w->addRow($s9, [['v' => '', 's' => 0, 'type' => 'str']], 10);
    $rW = $w->addRow($s9, [['v' => '🗑️ سجل الهالك والتالف', 's' => 3, 'type' => 'str']], 24);
    $w->mergeRow($s9, $rW, 1, 7);
    $w->addRow($s9, [
        ['v' => 'الصنف', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'الكمية', 's' => 4, 'type' => 'str'],
        ['v' => 'الوحدة', 's' => 4, 'type' => 'str'],
        ['v' => 'التكلفة التقديرية', 's' => 4, 'type' => 'str'],
        ['v' => 'السبب', 's' => 4, 'type' => 'str'],
        ['v' => 'المسؤول والتاريخ', 's' => 4, 'type' => 'str'],
    ], 24);
    $wStart = null;
    $wEnd   = null;
    foreach ($data['waste'] as $idx => $ws) {
        $z = ($idx % 2 === 1);
        $rn = $w->addRow($s9, [
            ['v' => $ws['product_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ws['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $ws['quantity'], 's' => $z ? 10 : 9, 'type' => 'num'],
            ['v' => $ws['unit'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $ws['est_cost'], 's' => 14, 'type' => 'num'],
            ['v' => $ws['reason'] ?: '—', 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $ws['user_name'] . ' (' . $ws['created_at'] . ')', 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 21);
        if ($wStart === null) $wStart = $rn;
        $wEnd = $rn;
    }
    if ($wStart !== null) {
        $w->addRow($s9, [
            ['v' => 'إجمالي تكلفة الهالك', 's' => 11, 'type' => 'str'],
            ['v' => count($data['waste']) . ' عملية', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($wStart, $wEnd, 3, 13),
            ['v' => '', 's' => 11, 'type' => 'str'],
            xlsx_sum_cell($wStart, $wEnd, 5, 12),
            ['v' => '', 's' => 11, 'type' => 'str'],
            ['v' => '', 's' => 11, 'type' => 'str'],
        ], 24);
    }

    // 9C Shifts
    $w->addRow($s9, [['v' => '', 's' => 0, 'type' => 'str']], 10);
    $rS = $w->addRow($s9, [['v' => '⏱️ ملخص تقفيل الشيفتات', 's' => 3, 'type' => 'str']], 24);
    $w->mergeRow($s9, $rS, 1, 7);
    $w->addRow($s9, [
        ['v' => 'تاريخ الشيفت', 's' => 4, 'type' => 'str'],
        ['v' => 'الفرع', 's' => 4, 'type' => 'str'],
        ['v' => 'الكاشير', 's' => 4, 'type' => 'str'],
        ['v' => 'المتوقع كاش', 's' => 4, 'type' => 'str'],
        ['v' => 'الفعلي بالدرج', 's' => 4, 'type' => 'str'],
        ['v' => 'العجز / الزيادة', 's' => 4, 'type' => 'str'],
        ['v' => 'عدد الفواتير والمحول', 's' => 4, 'type' => 'str'],
    ], 24);
    foreach ($data['shifts'] as $idx => $sh) {
        $z = ($idx % 2 === 1);
        $diff = (float) $sh['difference'];
        $w->addRow($s9, [
            ['v' => $sh['shift_date'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $sh['location_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => $sh['user_name'], 's' => $z ? 6 : 5, 'type' => 'str'],
            ['v' => (float) $sh['expected_cash'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => (float) $sh['actual_cash'], 's' => $z ? 8 : 7, 'type' => 'num'],
            ['v' => $diff, 's' => $diff < -0.01 ? 14 : ($z ? 8 : 7), 'type' => 'num'],
            ['v' => (int) $sh['total_invoices'] . ' فاتورة | محول: ' . number_format((float) $sh['cash_transferred'], 2), 's' => $z ? 6 : 5, 'type' => 'str'],
        ], 21);
    }
    $w->setMinColWidths($s9, [1 => 22, 2 => 18, 3 => 18, 4 => 18, 5 => 16, 6 => 18, 7 => 32]);

    $w->save($targetPath);
}

function output_monthly_report_xlsx(array $data): never
{
    $tmp = tempnam(sys_get_temp_dir(), 'hmz_xlsx_');
    if ($tmp === false) {
        throw new RuntimeException('تعذر إنشاء ملف مؤقت للتصدير.');
    }
    try {
        build_monthly_report_xlsx_file($data, $tmp);
        $filename = 'hamza-monthly-report-' . $data['month'] . '.xlsx';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');
        readfile($tmp);
    } finally {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
    exit;
}

function render_monthly_report_pdf_html(array $data): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'حمزة للعطور';
    $appBase  = defined('APP_BASE') ? APP_BASE : '';
    $logoUrl  = ($appBase !== '' ? rtrim($appBase, '/') . '/' : '') . 'assets/logo.png';
    $sum      = $data['summary'];
    $excelUrl = 'index.php?' . http_build_query(array_filter([
        'r'           => 'reports',
        'export'      => 'monthly_excel',
        'month'       => $data['month'],
        'location_id' => $data['location_id'] ?? '',
    ], static fn($v) => $v !== ''));

    $fmtMoney = static fn(float $v): string => number_format($v, 2) . ' ج.م';
    $fmtQty   = static fn(float $v): string => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

    ob_start();
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>التقرير الشهري الشامل — <?= e($data['month_label']) ?> — <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 11mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            margin: 0;
            padding: 0;
            background: #EFECE6;
            color: #1F160D;
            font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 999;
            background: #1C140D;
            color: #F5E6C4;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 3px solid #C9A84C;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }
        .action-bar-title {
            font-size: 14px;
            font-weight: 800;
            color: #E8C96A;
        }
        .action-bar-btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .p-btn {
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .p-btn.gold { background: linear-gradient(135deg, #E8C96A, #C9A84C); color: #1C140D; }
        .p-btn.green { background: #10B981; color: #FFFFFF; }
        .p-btn.dark { background: #332518; color: #F5E6C4; border: 1px solid #6E5224; }

        .sheet {
            width: 210mm;
            max-width: 100%;
            margin: 18px auto 36px;
            background: #FFFFFF;
            padding: 16mm 14mm;
            box-shadow: 0 12px 40px rgba(28, 20, 13, 0.14);
            border-radius: 8px;
        }
        .exec-header {
            background: linear-gradient(135deg, #1C140D 0%, #2E2014 100%);
            color: #FAF6EE;
            border: 2px solid #C9A84C;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }
        .exec-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .exec-brand img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            background: #FFFFFF;
            border-radius: 10px;
            padding: 4px;
            border: 1.5px solid #C9A84C;
        }
        .exec-brand h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            color: #E8C96A;
        }
        .exec-brand p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #E5D7B8;
        }
        .exec-meta {
            text-align: left;
            font-size: 11.5px;
            line-height: 1.75;
            color: #F5E6C4;
        }
        .exec-meta strong {
            color: #E8C96A;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .kpi-card {
            background: #FAF6EE;
            border: 1px solid #DFD3BC;
            border-top: 3.5px solid #C9A84C;
            border-radius: 10px;
            padding: 10px 12px;
            break-inside: avoid;
        }
        .kpi-card.green { border-top-color: #10B981; background: #F1FBF6; }
        .kpi-card.red { border-top-color: #DC2626; background: #FEF4F4; }
        .kpi-card.amber { border-top-color: #D97706; background: #FFFBEB; }
        .kpi-card.indigo { border-top-color: #4F46E5; background: #F5F6FF; }
        .kpi-title { font-size: 10.5px; font-weight: 700; color: #6B573D; margin-bottom: 4px; }
        .kpi-val { font-size: 15.5px; font-weight: 900; color: #1C140D; }
        .kpi-card.green .kpi-val { color: #065F46; }
        .kpi-card.red .kpi-val { color: #991B1B; }
        .kpi-sub { font-size: 10px; color: #7C6951; margin-top: 2px; }

        .sec-block {
            margin-bottom: 18px;
        }
        .sec-title {
            background: linear-gradient(90deg, #F6EFE0, #EAD8B1);
            border-right: 4px solid #B88928;
            color: #2C2014;
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 900;
            margin: 0 0 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            break-after: avoid;
        }
        .sec-title small { font-weight: 700; color: #6F4A08; font-size: 11px; }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }
        .bar-row {
            margin-bottom: 8px;
        }
        .bar-head {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .bar-track {
            height: 8px;
            background: #EFE6D5;
            border-radius: 999px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #B88928, #E8C96A);
            border-radius: 999px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.8px;
            margin-bottom: 6px;
        }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th {
            background: #2C2014;
            color: #F5E6C4;
            padding: 6px 7px;
            text-align: right;
            font-weight: 800;
            border: 1px solid #4A3622;
        }
        td {
            padding: 5px 7px;
            border: 1px solid #E5DAC6;
            color: #1F160D;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td {
            background: #FAF6EE;
        }
        tfoot td {
            background: #E6F4EA !important;
            color: #065F46;
            font-weight: 900;
            border-top: 2px solid #10B981;
        }
        .num { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .neg { color: #991B1B; font-weight: 800; }
        .pos { color: #065F46; font-weight: 800; }
        .empty-note {
            padding: 10px 12px;
            background: #FAF6EE;
            border: 1px dashed #D8C7A6;
            border-radius: 6px;
            color: #7C6951;
            font-size: 11px;
            text-align: center;
        }

        .sign-footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 2px dashed #C9A84C;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            text-align: center;
            break-inside: avoid;
        }
        .sign-box {
            background: #FAF6EE;
            border: 1px solid #DFD3BC;
            border-radius: 8px;
            padding: 12px 10px 28px;
            font-size: 11.5px;
            font-weight: 800;
            color: #3A2916;
        }
        .page-break { page-break-before: always; break-before: page; }

        @media print {
            body { background: #FFFFFF; }
            .no-print { display: none !important; }
            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

<div class="action-bar no-print">
    <div class="action-bar-title">📄 معاينة التقرير الشهري الشامل — <?= e($data['month_label']) ?> (<?= e($data['location_name']) ?>)</div>
    <div class="action-bar-btns">
        <button type="button" class="p-btn gold" onclick="window.print()">🖨️ حفظ كملف PDF / طباعة</button>
        <a class="p-btn green" href="<?= e($excelUrl) ?>">📊 تحميل شيت Excel (.xlsx)</a>
        <a class="p-btn dark" href="index.php?r=reports">↩️ العودة للتقارير</a>
    </div>
</div>

<main class="sheet">
    <!-- Executive Header -->
    <header class="exec-header">
        <div class="exec-brand">
            <img src="<?= e($logoUrl) ?>" alt="Logo" onerror="this.style.display='none'">
            <div>
                <h1><?= e($appName) ?></h1>
                <p>التقرير المالي والتشغيلي الشهري الشامل</p>
            </div>
        </div>
        <div class="exec-meta">
            <div><strong>الشهر:</strong> <?= e($data['month_label']) ?></div>
            <div><strong>الفترة:</strong> <?= e($data['start_date']) ?> إلى <?= e($data['end_date']) ?></div>
            <div><strong>النطاق:</strong> <?= e($data['location_name']) ?></div>
            <div><strong>الإصدار:</strong> <?= e($data['generated_at']) ?></div>
        </div>
    </header>

    <!-- 8 Executive KPI Cards -->
    <section class="kpi-grid">
        <div class="kpi-card green">
            <div class="kpi-title">صافي المبيعات المفوترة</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['net_sales'])) ?></div>
            <div class="kpi-sub">قبل الخصم: <?= e($fmtMoney($sum['gross_sales'])) ?> | خصم: <?= e($fmtMoney($sum['total_discounts'])) ?></div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-title">إجمالي التحصيل الفعلي</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['total_collected'])) ?></div>
            <div class="kpi-sub">فواتير: <?= e($fmtMoney($sum['invoice_payments_total'])) ?> | ديون: <?= e($fmtMoney($sum['debt_payments_total'])) ?></div>
        </div>
        <div class="kpi-card red">
            <div class="kpi-title">إجمالي المصاريف التشغيلية</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['total_expenses'])) ?></div>
            <div class="kpi-sub">عدد البنود المسجلة: <?= (int) $sum['expenses_count'] ?> مصروف</div>
        </div>
        <div class="kpi-card <?= $sum['net_cash_flow'] >= 0 ? 'green' : 'red' ?>">
            <div class="kpi-title">صافي التدفق النقدي</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['net_cash_flow'])) ?></div>
            <div class="kpi-sub">التحصيل − (المصاريف + المرتجع + الرواتب)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">عدد الفواتير ومتوسط الفاتورة</div>
            <div class="kpi-val"><?= (int) $sum['invoices_count'] ?> فاتورة</div>
            <div class="kpi-sub">متوسط الفاتورة: <?= e($fmtMoney($sum['avg_invoice'])) ?></div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-title">الديون المتبقية والمرتجعات</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['total_due'])) ?></div>
            <div class="kpi-sub">مرتجع مدفوع: <?= e($fmtMoney($sum['total_refund_paid'])) ?> (<?= (int) $sum['returns_count'] ?>)</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-title">الرواتب المنصرفة (HR)</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['total_salaries'])) ?></div>
            <div class="kpi-sub">لعدد <?= (int) $sum['salaries_count'] ?> موظف في مسير <?= e($data['month']) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">تحويلات المدير والعملاء الجدد</div>
            <div class="kpi-val"><?= e($fmtMoney($sum['total_transfers'])) ?></div>
            <div class="kpi-sub">عملاء جدد: <?= (int) $sum['new_customers_count'] ?> | هالك: <?= e($fmtMoney($sum['total_waste_cost'])) ?></div>
        </div>
    </section>

    <!-- Payments & Branches -->
    <section class="two-col">
        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>💳 التحصيلات حسب طريقة الدفع</span><small><?= e($fmtMoney($sum['total_collected'])) ?></small></h3>
            <?php if ($data['payments_by_method']): ?>
                <?php foreach ($data['payments_by_method'] as $pm):
                    $pct = $sum['total_collected'] > 0 ? round(((float) $pm['total'] / $sum['total_collected']) * 100, 1) : 0;
                ?>
                    <div class="bar-row">
                        <div class="bar-head">
                            <span><?= e(monthly_report_payment_label($pm['method'])) ?> (<?= (int) $pm['tx_count'] ?>)</span>
                            <span><?= e($fmtMoney((float) $pm['total'])) ?> — <?= $pct ?>%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= min(100, max(2, $pct)) ?>%;"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-note">لا توجد تحصيلات مسجلة خلال هذا الشهر.</div>
            <?php endif; ?>
        </div>

        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>🏬 المبيعات حسب الفرع</span></h3>
            <?php if ($data['sales_by_location']): ?>
                <table>
                    <thead><tr><th>الفرع</th><th class="num">الفواتير</th><th class="num">الصافي</th><th class="num">المتبقي</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['sales_by_location'] as $loc): ?>
                            <tr>
                                <td><strong><?= e($loc['name']) ?></strong></td>
                                <td class="num"><?= (int) $loc['invoices_count'] ?></td>
                                <td class="num pos"><?= e($fmtMoney((float) $loc['total'])) ?></td>
                                <td class="num"><?= e($fmtMoney((float) $loc['due_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-note">لا توجد مبيعات مسجلة للفروع.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Expenses Summary & Details -->
    <section class="sec-block">
        <h3 class="sec-title"><span>💸 المصاريف التشغيلية حسب الفئة والتفاصيل</span><small>الإجمالي: <?= e($fmtMoney($sum['total_expenses'])) ?></small></h3>
        <?php if ($data['expenses']): ?>
            <div class="two-col" style="margin-bottom: 10px;">
                <table>
                    <thead><tr><th>فئة المصروف</th><th class="num">العدد</th><th class="num">الإجمالي</th><th class="num">النسبة</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['expenses_by_category'] as $ec):
                            $pct = $sum['total_expenses'] > 0 ? round(((float) $ec['total'] / $sum['total_expenses']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?= e($ec['category_name']) ?></strong></td>
                                <td class="num"><?= (int) $ec['count'] ?></td>
                                <td class="num neg"><?= e($fmtMoney((float) $ec['total'])) ?></td>
                                <td class="num"><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="empty-note" style="display:flex; flex-direction:column; justify-content:center;">
                    <strong>ملخص مصاريف <?= e($data['month_label']) ?></strong>
                    <div style="margin-top:6px;">تم صرف إجمالي <strong style="color:#991B1B;"><?= e($fmtMoney($sum['total_expenses'])) ?></strong> عبر <strong><?= (int) $sum['expenses_count'] ?></strong> عملية مصرف خلال الشهر.</div>
                </div>
            </div>
            <table>
                <thead><tr><th>التاريخ</th><th>البند</th><th>الفرع</th><th>طريقة الدفع</th><th class="num">المبلغ</th><th>المسؤول</th><th>ملاحظات</th></tr></thead>
                <tbody>
                    <?php foreach ($data['expenses'] as $ex): ?>
                        <tr>
                            <td class="num"><?= e($ex['expense_date']) ?></td>
                            <td><strong><?= e($ex['category_name']) ?></strong></td>
                            <td><?= e($ex['location_name']) ?></td>
                            <td><?= e(monthly_report_payment_label($ex['payment_method'])) ?></td>
                            <td class="num neg"><?= e($fmtMoney((float) $ex['amount'])) ?></td>
                            <td><?= e($ex['user_name']) ?></td>
                            <td><?= e($ex['notes'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4">إجمالي المصاريف</td><td class="num"><?= e($fmtMoney($sum['total_expenses'])) ?></td><td colspan="2"></td></tr></tfoot>
            </table>
        <?php else: ?>
            <div class="empty-note">لا توجد مصاريف مسجلة خلال هذا الشهر.</div>
        <?php endif; ?>
    </section>

    <!-- Staff Performance & Salaries -->
    <section class="two-col">
        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>👤 أداء المبيعات للموظفين</span></h3>
            <?php if ($data['sales_by_user']): ?>
                <table>
                    <thead><tr><th>الموظف</th><th>الفرع</th><th class="num">الفواتير</th><th class="num">المبيعات</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['sales_by_user'] as $su): ?>
                            <tr>
                                <td><strong><?= e($su['name']) ?></strong></td>
                                <td><?= e($su['location_name']) ?></td>
                                <td class="num"><?= (int) $su['invoices_count'] ?></td>
                                <td class="num pos"><?= e($fmtMoney((float) $su['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-note">لا توجد مبيعات مسجلة للموظفين.</div>
            <?php endif; ?>
        </div>

        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>💼 الرواتب المنصرفة (HR)</span><small><?= e($fmtMoney($sum['total_salaries'])) ?></small></h3>
            <?php if ($data['salaries']): ?>
                <table>
                    <thead><tr><th>الموظف</th><th class="num">الأساسي</th><th class="num">العمولة</th><th class="num">الصافي</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['salaries'] as $sal): ?>
                            <tr>
                                <td><strong><?= e($sal['user_name']) ?></strong></td>
                                <td class="num"><?= e($fmtMoney((float) $sal['basic_salary'])) ?></td>
                                <td class="num"><?= e($fmtMoney((float) $sal['commission'])) ?></td>
                                <td class="num pos"><?= e($fmtMoney((float) $sal['net_payout'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-note">لا توجد مسيرات رواتب مصروفة لهذا الشهر.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Products & Perfumes -->
    <section class="two-col">
        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>🛍️ أعلى الأصناف والتركيبات مبيعاً</span></h3>
            <?php $topSlice = array_slice($data['top_products'], 0, 15); ?>
            <?php if ($topSlice): ?>
                <table>
                    <thead><tr><th>الصنف</th><th class="num">الكمية</th><th class="num">الإجمالي</th></tr></thead>
                    <tbody>
                        <?php foreach ($topSlice as $tp): ?>
                            <tr>
                                <td><?= e($tp['description']) ?></td>
                                <td class="num"><?= e($fmtQty((float) $tp['qty_sold'])) ?></td>
                                <td class="num pos"><?= e($fmtMoney((float) $tp['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-note">لا توجد مبيعات أصناف مسجلة.</div>
            <?php endif; ?>
        </div>

        <div class="sec-block" style="margin-bottom:0;">
            <h3 class="sec-title"><span>🧴 استهلاك الزيوت العطرية (بالجرام)</span></h3>
            <?php $perfSlice = array_slice($data['perfume_usage'], 0, 15); ?>
            <?php if ($perfSlice): ?>
                <table>
                    <thead><tr><th>الزيت العطري</th><th class="num">الجرامات</th><th class="num">التكلفة</th></tr></thead>
                    <tbody>
                        <?php foreach ($perfSlice as $pu): ?>
                            <tr>
                                <td><?= e($pu['name']) ?></td>
                                <td class="num"><?= e($fmtQty((float) $pu['grams'])) ?> جم</td>
                                <td class="num"><?= e($fmtMoney((float) $pu['est_cost'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-note">لا يوجد استهلاك زيوت عطرية مسجل.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Returns, Transfers & Waste -->
    <?php if ($data['returns'] || $data['cash_transfers'] || $data['waste'] || $data['shifts']): ?>
        <section class="sec-block">
            <h3 class="sec-title"><span>🔄 المرتجعات، التحويلات، الهالك والشيفتات</span></h3>
            <?php if ($data['returns']): ?>
                <table>
                    <thead><tr><th>رقم المرتجع</th><th>الفاتورة الأصلية</th><th>الفرع</th><th>طريقة الاسترداد</th><th class="num">القيمة</th><th class="num">المسترد المدفوع</th><th>السبب</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['returns'] as $ret): ?>
                            <tr>
                                <td><?= e($ret['return_number']) ?></td>
                                <td><?= e($ret['invoice_number']) ?></td>
                                <td><?= e($ret['location_name']) ?></td>
                                <td><?= e(monthly_report_payment_label($ret['refund_method'])) ?></td>
                                <td class="num neg"><?= e($fmtMoney((float) $ret['amount'])) ?></td>
                                <td class="num neg"><?= e($fmtMoney((float) $ret['refund_paid'])) ?></td>
                                <td><?= e($ret['reason'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($data['cash_transfers']): ?>
                <table style="margin-top: 8px;">
                    <thead><tr><th>رقم التحويل</th><th>التاريخ</th><th>الفرع</th><th>الطريقة</th><th class="num">المبلغ</th><th>الحالة</th><th>المستلم</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['cash_transfers'] as $tr): ?>
                            <tr>
                                <td><?= e($tr['transfer_number']) ?></td>
                                <td class="num"><?= e($tr['transfer_date']) ?></td>
                                <td><?= e($tr['location_name']) ?></td>
                                <td><?= e(monthly_report_payment_label($tr['method'])) ?></td>
                                <td class="num pos"><?= e($fmtMoney((float) $tr['amount'])) ?></td>
                                <td><?= e(monthly_report_transfer_status_label($tr['status'])) ?></td>
                                <td><?= e($tr['received_by_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($data['waste']): ?>
                <table style="margin-top: 8px;">
                    <thead><tr><th>صنف الهالك</th><th>الفرع</th><th class="num">الكمية</th><th class="num">التكلفة</th><th>السبب</th><th>المسؤول</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['waste'] as $ws): ?>
                            <tr>
                                <td><?= e($ws['product_name']) ?></td>
                                <td><?= e($ws['location_name']) ?></td>
                                <td class="num"><?= e($fmtQty((float) $ws['quantity'])) ?> <?= e($ws['unit']) ?></td>
                                <td class="num neg"><?= e($fmtMoney((float) $ws['est_cost'])) ?></td>
                                <td><?= e($ws['reason'] ?: '—') ?></td>
                                <td><?= e($ws['user_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Complete Invoices Appendix -->
    <section class="sec-block <?= count($data['invoices']) > 8 ? 'page-break' : '' ?>">
        <h3 class="sec-title"><span>🧾 سجل فواتير المبيعات الكامل</span><small>الإجمالي: <?= (int) $sum['invoices_count'] ?> فاتورة — <?= e($fmtMoney($sum['net_sales'])) ?></small></h3>
        <?php if ($data['invoices']): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الفرع</th>
                        <th>الكاشير</th>
                        <th>العميل</th>
                        <th>الدفع</th>
                        <th class="num">الخصم</th>
                        <th class="num">الصافي</th>
                        <th class="num">المتبقي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['invoices'] as $idx => $inv): ?>
                        <tr>
                            <td class="num"><?= $idx + 1 ?></td>
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td class="num"><?= e(date('d/m H:i', strtotime($inv['created_at']))) ?></td>
                            <td><?= e($inv['location_name']) ?></td>
                            <td><?= e($inv['user_name']) ?></td>
                            <td><?= e($inv['customer_name']) ?></td>
                            <td><?= e(monthly_report_payment_label($inv['payment_methods'] ?? '')) ?></td>
                            <td class="num"><?= e($fmtMoney((float) $inv['discount_amount'])) ?></td>
                            <td class="num pos"><?= e($fmtMoney((float) $inv['total'])) ?></td>
                            <td class="num <?= (float) $inv['due_total'] > 0 ? 'neg' : '' ?>"><?= e($fmtMoney((float) $inv['due_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">الإجمالي الكلي لفواتير الشهر</td>
                        <td class="num"><?= e($fmtMoney($sum['total_discounts'])) ?></td>
                        <td class="num"><?= e($fmtMoney($sum['net_sales'])) ?></td>
                        <td class="num"><?= e($fmtMoney($sum['total_due'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="empty-note">لا توجد فواتير مسجلة خلال هذا الشهر.</div>
        <?php endif; ?>
    </section>

    <?php if ($data['new_customers']): ?>
        <section class="sec-block">
            <h3 class="sec-title"><span>👥 العملاء الجدد المسجلون</span><small><?= count($data['new_customers']) ?> عميل</small></h3>
            <table>
                <thead><tr><th>#</th><th>اسم العميل</th><th>الهاتف</th><th>تاريخ الميلاد</th><th>المصدر</th><th>الفرع</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php foreach ($data['new_customers'] as $idx => $nc): ?>
                        <tr>
                            <td class="num"><?= $idx + 1 ?></td>
                            <td><strong><?= e($nc['name']) ?></strong></td>
                            <td class="num" dir="ltr"><?= e($nc['phone'] ?: '—') ?></td>
                            <td class="num"><?= e($nc['birthdate'] ?: '—') ?></td>
                            <td><?= ($nc['source'] ?? '') === 'online' ? 'أونلاين' : 'فرع' ?></td>
                            <td><?= e($nc['location_name']) ?></td>
                            <td class="num"><?= e(date('d/m/Y', strtotime($nc['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <!-- Formal Signature Footer -->
    <footer class="sign-footer">
        <div class="sign-box">إعداد / المحاسب المسؤول</div>
        <div class="sign-box">مراجعة / مدير الفرع</div>
        <div class="sign-box">اعتماد / الإدارة العامة</div>
    </footer>
</main>

</body>
</html>
    <?php
    return (string) ob_get_clean();
}

function output_monthly_report_pdf_view(array $data): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/html; charset=utf-8');
    echo render_monthly_report_pdf_html($data);
    exit;
}
