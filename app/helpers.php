<?php

declare(strict_types=1);

// Initialize language once at the very start of the request
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'en' ? 'en' : 'ar';
    $_SESSION['lang'] = $lang;
    if (!headers_sent()) {
        setcookie('lang', $lang, time() + 365 * 24 * 60 * 60, '/');
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function money(mixed $value): string
{
    if (current_lang() === 'en') {
        return 'EGP ' . number_format((float) $value, 2);
    }
    return number_format((float) $value, 2) . ' ج.م';
}

function qty(mixed $value): string
{
    return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
}

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) return '-';
    $time = strtotime($datetime);
    $formatted = date('d/m/Y h:i A', $time);
    return str_replace(['AM', 'PM'], ['صباحاً', 'مساءً'], $formatted);
}

function format_date(?string $date): string
{
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

function format_time(?string $timeStr): string
{
    if (empty($timeStr)) return '-';
    $time = strtotime($timeStr);
    $formatted = date('h:i A', $time);
    return str_replace(['AM', 'PM'], ['صباحاً', 'مساءً'], $formatted);
}

function redirect(string $route): never
{
    $parts = explode('&', $route, 2);
    $target = APP_BASE . '/index.php?r=' . urlencode($parts[0]);

    if (isset($parts[1]) && $parts[1] !== '') {
        parse_str($parts[1], $query);
        if ($query) {
            $target .= '&' . http_build_query($query);
        }
    }

    header('Location: ' . $target);
    exit;
}

function route(): string
{
    $r = $_GET['r'] ?? $_GET['page'] ?? $_POST['r'] ?? $_POST['page'] ?? 'dashboard';
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $r) ?: 'dashboard';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('انتهت صلاحية الطلب. أعد تحميل الصفحة.');
    }
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_float(string $key, float $default = 0): float
{
    return (float) str_replace(',', '.', (string) ($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function current_lang(): string
{
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }
    if (isset($_COOKIE['lang'])) {
        return $_COOKIE['lang'];
    }
    return 'ar'; // default
}

function __($text): string
{
    if (!is_string($text) && !is_numeric($text)) {
        return '';
    }
    $textStr = (string) $text;
    if (current_lang() === 'ar') {
        return $textStr;
    }
    
    static $dictionary = null;
    if ($dictionary === null) {
        $dictionary = [
            // General
            'لوحة التحكم' => 'Dashboard',
            'الرئيسية' => 'Home',
            'بيع جديد' => 'POS / Sell',
            'التنبيهات' => 'Notifications',
            'التقارير' => 'Reports',
            'عام' => 'General',
            'كل الفروع' => 'All Branches',
            'الفرع' => 'Branch',
            'تصفية' => 'Filter',
            'إعادة ضبط' => 'Reset',
            'إجراء' => 'Action',
            'التفاصيل' => 'Details',
            'التاريخ' => 'Date',
            'المستخدم' => 'User',
            'العملية' => 'Operation',
            'الكيان' => 'Entity',
            'رقم الكيان' => 'Entity ID',
            'سجل العمليات الأخير' => 'Recent Operations Log',
            'تم العثور على' => 'Found',
            'سجل' => 'records',
            'حفظ' => 'Save',
            'حذف' => 'Delete',
            'إلغاء' => 'Cancel',
            'تعديل' => 'Edit',
            'ملاحظات' => 'Notes',
            'إضافة' => 'Add',
            'حالة' => 'Status',
            'الموقع' => 'Location',
            'الموظف' => 'Employee',
            'العميل' => 'Customer',
            'الإجمالي' => 'Total',
            'المدفوع' => 'Paid',
            'المبيعات' => 'Sales',
            'المتبقي' => 'Due',
            'بحث' => 'Search',
            'اسم العميل' => 'Customer Name',
            'رقم الفاتورة' => 'Invoice Number',
            'تعديل الملاحظة' => 'Edit Note',
            'زبون عابر' => 'Walk-in Customer',
            'تعديل إعدادات النظام' => 'Edit System Settings',
            'تحديث حالة' => 'Update Status',
            'نسخ احتياطي' => 'Backup',
            'تسجيل دخول' => 'Login',
            'تسجيل خروج' => 'Logout',
            'إضافة / إنشاء' => 'Create / Add',
            'تعديل بيانات' => 'Edit Info',
            'حذف نهائي' => 'Delete Permanently',
            'تعطيل / إخفاء' => 'Deactivate / Hide',
            'تسوية مخزون' => 'Stock Adjustment',
            'استلام شحنة' => 'Receive Transfer',
            'مرتجع بند مبيعات' => 'Sales Return Line',
            'قاعدة البيانات' => 'Database',
            'طلب أونلاين' => 'Online Order',
            'تحويل مخزني' => 'Stock Transfer',
            'بند فاتورة مبيعات' => 'Invoice Line Item',
            'موظف / مستخدم' => 'Employee / User',
            'قائمة المستخدمين' => 'User List',
            'الأدوار والصلاحيات' => 'Roles & Permissions',
            'الحضور والانصراف' => 'Attendance Logs',
            'تسجيل حضور بالكاميرا' => 'QR Attendance Camera',
            'سجل الحضور اليومي' => 'Daily Attendance Log',
            'توليد رموز الفروع' => 'Generate Branch QR Codes',
            'طباعة' => 'Print',
            'الموارد البشرية' => 'Human Resources',
            'الأونلاين والموقع' => 'Online & Web',
            'النظام والمتابعة' => 'System & Followup',
            'إدارة العملاء' => 'Customer Management',
            'المنتجات والمخزون' => 'Products & Inventory',
            'الحسابات والمصاريف' => 'Finance & Expenses',
            'سجل الأنشطة والعمليات' => 'System Activity Log',
            'النسخ الاحتياطي' => 'Database Backup',
            'الإعدادات' => 'Settings',
            'رصيد الفرع' => 'Branch Balance',
            'إدارة المنتجات' => 'Product Management',
            'تركيبات العطور' => 'Saved Perfume Recipes',
            'حركة وجرد المخازن' => 'Stock Adjustments',
            'التحويلات بين الفروع' => 'Stock Transfers',
            'المرتجع العام' => 'Sales Returns',
            'الرواتب والعمولات' => 'Payroll & Commissions',
            'الحضور والغياب' => 'Attendance Scanner',
            'سجل الشفتات' => 'Register Shifts',
            'التارجت والمبيعات' => 'Sales Targets',
            'الموردين والمشتريات' => 'Suppliers & Purchases',
            'المصاريف والنفقات' => 'Expenses Tracker',
            'صفحات الموقع' => 'Website Pages',
            'طلبات الأونلاين' => 'Online Orders',
            'سجل أنشطة وعمليات النظام' => 'System Activities & Operations Log',
            'بحث سريع داخل القائمة' => 'Quick Menu Search',
            'اسم المستخدم' => 'Username',
            'كلمة المرور' => 'Password',
            'تسجيل الدخول' => 'System Login',
            'نظام إدارة براند العطور' => 'نظام الحمزة للعطور',
            'دخول' => 'Login',
            'تثبيت النظام' => 'Install System',
            'غير مصرح لك بدخول هذه الصفحة.' => 'You are not authorized to access this page.',
            'عفواً، لا توجد نتائج مطابقة.' => 'Sorry, no matching results found.',
            'تاريخ الفاتورة' => 'Invoice Date',
            'اسم المنتج' => 'Product Name',
            'النوع' => 'Type',
            'الرمز SKU' => 'SKU',
            'الباركود' => 'Barcode',
            'سعر البيع' => 'Sale Price',
            'سعر التكلفة' => 'Cost Price',
            'الحد الأدنى' => 'Min Stock Alert',
            'الحجم (مل)' => 'Size (ml)',
            'الفئة العطرية' => 'Perfume Family',
            'درجة الكوتة' => 'Quota Grade',
            'سعر الجرام' => 'Price per Gram',
            'الجرامات الافتراضية للتركيب' => 'Default Grams for Mixing',
            'المستندات والفواتير' => 'Documents & Invoices',
            'رقم العملية' => 'Transaction ID',
            'المرتجع' => 'Return',
            'صافي الربح' => 'Net Profit',
            'إجمالي المبيعات' => 'Total Sales',
            'إجمالي النفقات' => 'Total Expenses',
            'أكثر المنتجات مبيعاً' => 'Top Selling Products',
            'استهلاك الزيوت (جرام)' => 'Oil Consumption (grams)',
            'العملاء الجدد' => 'New Customers Register',
            'المورد' => 'Supplier',
            'الهاتف' => 'Phone',
            'نوع المنتجات' => 'Product Type',
            'رقم فاتورة واردة' => 'Inward Invoice Number',
            'قيمة الفاتورة' => 'Invoice Amount',
            'الفئة' => 'Category',
            'المبلغ' => 'Amount',
            'مصروفات الموقع' => 'Expenses of Location',
            'نوع الحساب' => 'Account Type',
            'مخزن رئيسي' => 'Main Warehouse',
            'معرض مبيعات' => 'Retail Showroom',
            'أونلاين' => 'Online',
            'رصيد الديون' => 'Debt Balance',
            'تغيير كلمة المرور' => 'Change Password',
            'الصلاحية' => 'Permission / Role',
            'تفاصيل الراتب' => 'Salary details',
            'الراتب الأساسي' => 'Basic Salary',
            'نسبة العمولات' => 'Commission Rate %',
            'أيام الحضور' => 'Present Days',
            'ساعات التأخير' => 'Delay Hours',
            'العمولة المحققة' => 'Commissions Earned',
            'صافي الراتب المستحق' => 'Net Salary Payable',
            'حفظ التغييرات' => 'Save Changes',
            'صنع بـ ❤️' => 'Made with ❤️',
            'جميع الحقوق محفوظة' => 'All Rights Reserved',
            'تتبع وتحليل كافة العمليات الحساسة والتغييرات التي تمت بالنظام من مبيعات، تحديثات المنتجات، تسويات المخزون، الحضور، وتغيير الصلاحيات.' => 'Track and analyze all sensitive operations and system changes such as sales, product updates, stock adjustments, attendance, and permissions.',
            'بحث في التفاصيل والكيانات' => 'Search Details & Entities',
            'بحث عن كلمة مفتاحية...' => 'Search keyword...',
            'نوع العملية' => 'Operation Type',
            'من تاريخ' => 'From Date',
            'إلى تاريخ' => 'To Date',
            'كل العمليات' => 'All Operations',
            'كل المستخدمين' => 'All Users',
            'التاريخ والوقت' => 'Date & Time',
            'المستخدم / الموظف' => 'User / Employee',
            'الكيان المتأثر' => 'Affected Entity',
            'تفاصيل العملية' => 'Operation Details',
            'لا توجد سجلات مطابقة للبحث أو الفلترة المحددة.' => 'No records match the specified search or filter.',
            'سيستم تلقائي' => 'System Automatic',
            'تم عرض' => 'Showing',
            'سجل مفلتر' => 'filtered records',
            'نظرة مباشرة' => 'At a Glance',
            'لوحة التحكم العامة' => 'General Dashboard',
            'مبيعات الفروع والأونلاين، المخزون، الديون، والمصاريف في شاشة واحدة.' => 'Branch & online sales, stock, debts, and expenses in one screen.',
            'فتح الكاشير' => 'Open POS Cashier',
            'مبيعات اليوم' => 'Today\'s Sales',
            'فواتير اليوم' => 'Today\'s Invoices',
            'عملاء جدد اليوم' => 'New Customers Today',
            'إجمالي العملاء' => 'Total Customers',
            'ديون مفتوحة' => 'Open Debts',
            'مصاريف الشهر' => 'This Month Expenses',
            'تنبيهات مخزون' => 'Low Stock Alerts',
            'مبيعات اليوم حسب القناة' => 'Today\'s Sales by Channel',
            'أصناف وصلت للحد الأدنى' => 'Products at Minimum Stock Level',
            'آخر الفواتير' => 'Recent Invoices',
            'الرصيد' => 'Balance',
            'الحد' => 'Limit',
            'معاينة ملصق الباركود (مقياس 50مم × 25مم)' => 'Barcode Sticker Preview (Scale 50mm × 25mm)',
            'طباعة الملصق' => 'Print Sticker',
            'العودة إلى إدارة المنتجات' => 'Return to Product Management',
            'إعدادات التركيبات' => 'Recipe Settings',
            'حفظ وصفات العطور الجاهزة لتسريع عمليات البيع في شاشة الكاشير.' => 'Save pre-made perfume recipes to speed up POS cashier sales.',
            'تعديل التركيبة' => 'Edit Recipe',
            'حفظ تركيبة جديدة' => 'Save New Recipe',
            'اسم التركيبة' => 'Recipe Name',
            'الزجاجة الافتراضية' => 'Default Bottle',
            'سعر البيع المقترح' => 'Suggested Sale Price',
            'مكونات التركيبة (الزيوت العطرية بالجرام)' => 'Recipe Components (Essential Oils in Grams)',
            'اختر الزيت العطري' => 'Select Essential Oil',
            '-- اختر زيتاً عطرياً --' => '-- Select an Essential Oil --',
            'الوزن بالجرام' => 'Weight in Grams',
            'أضف الزيت' => 'Add Oil',
            'الزيت العطري' => 'Essential Oil',
            'الوزن (جرام)' => 'Weight (Grams)',
            'التركيبات الجاهزة المحفوظة' => 'Saved Pre-made Recipes',
            'الزجاجة' => 'Bottle',
            'هل أنت متأكد من حذف هذه التركيبة؟' => 'Are you sure you want to delete this recipe?',
            'لا توجد تركيبات محفوظة حتى الآن.' => 'No saved recipes found yet.',
            'يرجى اختيار زيت عطري أولاً.' => 'Please select an essential oil first.',
            'يرجى إدخال وزن صحيح بالجرام.' => 'Please enter a valid weight in grams.',
            'هذا الزيت مضاف بالفعل للتركيبة.' => 'This oil is already added to the recipe.',
            'لم يتم إضافة مكونات زيتية بعد.' => 'No oil components added yet.',
            'يرجى إضافة مكون زيتي واحد على الأقل للتركيبة.' => 'Please add at least one oil component to the recipe.',
            'الجرامات الافتراضية' => 'Default Grams',
            'تحديد الجرامات الافتراضية لكل عائلة عطر وحجم زجاجة لتسريع إعداد التركيبات.' => 'Set default grams for each perfume family and bottle size to speed up recipe building.',
            'تعديل إعداد الجرامات' => 'Edit Grams Config',
            'إضافة إعداد جرامات افتراضية' => 'Add Default Grams Config',
            'عائلة العطر' => 'Perfume Family',
            'مثل: A, A+, X (اختياري)' => 'e.g., A, A+, X (Optional)',
            'حجم الزجاجة ml' => 'Bottle Size ml',
            'الجرام الافتراضي' => 'Default Gram',
            'حفظ التعديل' => 'Save Edit',
            'حفظ إعداد الجرامات' => 'Save Grams Config',
            'جدول الجرامات الافتراضية' => 'Default Grams Config Table',
            'حجم الزجاجة' => 'Bottle Size',
            'الجرامات' => 'Grams',
            'هل أنت متأكد من حذف هذا الإعداد؟' => 'Are you sure you want to delete this config?',
            'لا توجد إعدادات جرامات محفوظة حتى الآن.' => 'No saved default grams found yet.',
            'شرقي' => 'Oriental',
            'فرنسي' => 'French',
            'المنتجات' => 'Products',
            'التركيبات' => 'Recipes',
            'المخزون الموحد' => 'Central Inventory',
            'تحويلات المخزون' => 'Stock Transfers',
            'المرتجعات' => 'Returns',
            'الموظفون' => 'Employees',
            'تارجت المبيعات' => 'Sales Targets',
            'الموردين' => 'Suppliers',
            'خروج' => 'Logout',
            'حفظ تركيبة جاهزة' => 'Save Recipe',
            'حفظ التعديلات' => 'Save Changes',
            'جرام' => 'grams',
            'مثال: تركيبتي المميزة' => 'e.g. My Special Recipe',
            'مثال: 12.5' => 'e.g. 12.5',
            'مثل: A, A+, X (اختياري)' => 'e.g. A, A+, X (Optional)',
            'اختر زيتاً عطرياً' => 'Choose essential oil',
            // Invoice filters
            'الفواتير وسجل المبيعات' => 'Invoices & Sales Log',
            'بحث واستعراض الفواتير مع التصفية بالموظف والعميل وطريقة الدفع والتاريخ.' => 'Search and browse invoices filtered by employee, customer, payment method, and date.',
            'بحث في الفواتير' => 'Search Invoices',
            'رقم الفاتورة / اسم العميل' => 'Invoice # / Customer Name',
            'كل الموظفين' => 'All Employees',
            'كل العملاء' => 'All Customers',
            'طريقة الدفع' => 'Payment Method',
            'كل طرق الدفع' => 'All Payment Methods',
            'كاش / نقداً' => 'Cash',
            'إجمالي النتائج' => 'Total Results',
            'لا توجد فواتير مطابقة للفلاتر المحددة.' => 'No invoices match the selected filters.',
            'عرض/طباعة' => 'View / Print',
            // Dashboard stats
            'مبيعات اليوم الصافية' => 'Today\'s Net Sales',
            'فواتير اليوم' => 'Today\'s Invoices',
            'عملاء جدد اليوم' => 'New Customers Today',
            'إجمالي العملاء' => 'Total Customers',
            'ديون مفتوحة' => 'Open Debts',
            'مصاريف الشهر' => 'This Month Expenses',
            'تنبيهات مخزون' => 'Low Stock Alerts',
            'صنف' => 'items',
            'اليوم' => 'Today',
            'هذا الشهر' => 'This Month',
            'فاتورة' => 'invoice',
            'فاتورة اليوم' => 'Today\'s Invoice',
            // Dashboard additional
            'المخزون' => 'Inventory',
            'كل الفواتير' => 'All Invoices',
            'لا توجد فواتير حتى الآن.' => 'No invoices yet.',
            'لا توجد مبيعات اليوم بعد.' => 'No sales today yet.',
            'المخزون بمستويات جيدة' => 'Stock levels are healthy',
        ];
    }
    
    return $dictionary[$text] ?? (string) $text;
}

function calculate_distance(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000; // Earth radius in meters
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lng1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lng2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

function clean_whatsapp_phone(?string $phone): string
{
    $clean = preg_replace('/\D/', '', (string)$phone);
    if ($clean === '') {
        return '';
    }
    if (str_starts_with($clean, '0')) {
        return '2' . $clean;
    }
    if (!str_starts_with($clean, '2') && strlen($clean) === 10) {
        return '20' . $clean;
    }
    return $clean;
}

function clean_dial_phone(?string $phone): string
{
    return preg_replace('/[^\d+]/', '', (string)$phone);
}

/**
 * قائمة محافظات جمهورية مصر العربية الـ 27
 */
function egypt_governorates(): array
{
    return [
        'القاهرة',
        'الجيزة',
        'الإسكندرية',
        'القليوبية',
        'الغربية',
        'الشرقية',
        'الدقهلية',
        'المنوفية',
        'كفر الشيخ',
        'البحيرة',
        'دمياط',
        'بورسعيد',
        'الإسماعيلية',
        'السويس',
        'الفيوم',
        'بني سويف',
        'المنيا',
        'أسيوط',
        'سوهاج',
        'قنا',
        'الأقصر',
        'أسوان',
        'البحر الأحمر',
        'الوادي الجديد',
        'مطروح',
        'شمال سيناء',
        'جنوب سيناء',
    ];
}

/**
 * تسميات وحالات شحن الأوردرات
 */
function shipping_status_labels(): array
{
    return [
        'preparing' => ['label' => 'قيد التجهيز', 'badge' => 'warning', 'icon' => '⏳'],
        'shipped'   => ['label' => 'تم الشحن مع المندوب', 'badge' => 'info', 'icon' => '🚚'],
        'delivered' => ['label' => 'تم التسليم بنجاح', 'badge' => 'success', 'icon' => '✅'],
        'returned'  => ['label' => 'مرتجع', 'badge' => 'danger', 'icon' => '❌'],
        'cancelled' => ['label' => 'ملغي', 'badge' => 'muted', 'icon' => '🚫'],
    ];
}

/**
 * توليد باركود Code 39 بصيغة SVG فائقة الدقة والوضوح (100% Offline)
 */
function draw_barcode_svg(string $barcode, float $totalH = 46.0, float $textH = 11.0, float $narrow = 1.35): string
{
    $barcode = strtoupper(trim($barcode));
    if ($barcode === '') {
        return '';
    }

    $input = $barcode;
    if (substr($input, 0, 1) !== '*') {
        $input = '*' . $input;
    }
    if (substr($input, -1) !== '*') {
        $input = $input . '*';
    }

    $code39_map = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn'
    ];

    $wide = $narrow * 2.5;
    $gap  = $narrow;
    $barH = $totalH - $textH;

    $rects = [];
    $x = 8.0;

    for ($i = 0; $i < strlen($input); $i++) {
        $char = $input[$i];
        if (!isset($code39_map[$char])) {
            continue;
        }

        $pattern = $code39_map[$char];
        for ($j = 0; $j < 9; $j++) {
            $w = ($pattern[$j] === 'w') ? $wide : $narrow;
            if ($j % 2 === 0) {
                $rects[] = sprintf('<rect x="%.2f" y="0" width="%.2f" height="%.2f" fill="#000000" />', $x, $w, $barH);
            }
            $x += $w;
        }
        $x += $gap;
    }

    $totalWidth = $x + 8.0;
    $textY    = $totalH - 1.5;
    $textX    = $totalWidth / 2.0;
    $fontSize = $textH * 0.75;

    $svg  = sprintf(
        '<svg viewBox="0 0 %.2f %.2f" width="100%%" height="100%%" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">',
        $totalWidth, $totalH
    );
    $svg .= sprintf('<rect x="0" y="%.2f" width="%.2f" height="%.2f" fill="#ffffff" />', $barH, $totalWidth, $textH);
    $svg .= implode("\n", $rects);
    $svg .= sprintf(
        '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="\'Courier New\', Courier, monospace" font-size="%.2f" font-weight="bold" fill="#000000" letter-spacing="2.0">%s</text>',
        $textX, $textY, $fontSize, htmlspecialchars($barcode)
    );
    $svg .= '</svg>';

    return $svg;
}

/**
 * تحويل تاريخ ووقت إلى صياغة عربية ذكية سهلة القراءة للمدة المنقضية (منذ ساعتين، أمس، إلخ)
 */
function time_ago_ar(?string $datetime): string
{
    if (empty($datetime)) {
        return 'لم يُسجل بعد';
    }
    $time = strtotime($datetime);
    if (!$time) {
        return '-';
    }
    $diff = time() - $time;
    if ($diff < 0) {
        $diff = 0;
    }
    if ($diff < 60) {
        return 'الآن (منذ لحظات)';
    }
    $minutes = (int) floor($diff / 60);
    if ($minutes < 60) {
        if ($minutes === 1) return 'منذ دقيقة واحدة';
        if ($minutes === 2) return 'منذ دقيقتين';
        if ($minutes >= 3 && $minutes <= 10) return "منذ {$minutes} دقائق";
        return "منذ {$minutes} دقيقة";
    }
    $hours = (int) floor($diff / 3600);
    if ($hours < 24) {
        if ($hours === 1) return 'منذ ساعة واحدة';
        if ($hours === 2) return 'منذ ساعتين';
        if ($hours >= 3 && $hours <= 10) return "منذ {$hours} ساعات";
        return "منذ {$hours} ساعة";
    }
    $days = (int) floor($diff / 86400);
    if ($days < 7) {
        if ($days === 1) return 'أمس';
        if ($days === 2) return 'منذ يومين';
        if ($days >= 3 && $days <= 10) return "منذ {$days} أيام";
        return "منذ {$days} يوماً";
    }
    $weeks = (int) floor($days / 7);
    if ($days < 30) {
        if ($weeks === 1) return 'منذ أسبوع';
        if ($weeks === 2) return 'منذ أسبوعين';
        return "منذ {$weeks} أسابيع";
    }
    $months = (int) floor($days / 30);
    if ($days < 365) {
        if ($months === 1) return 'منذ شهر';
        if ($months === 2) return 'منذ شهرين';
        if ($months >= 3 && $months <= 10) return "منذ {$months} أشهر";
        return "منذ {$months} شهراً";
    }
    $years = (int) floor($days / 365);
    if ($years === 1) return 'منذ أكثر من سنة';
    if ($years === 2) return 'منذ سنتين';
    return "منذ {$years} سنوات";
}

/**
 * تسميات وشارات حركات المخزون بصيغة عربية معبرة
 */
function movement_type_ar(string $type, float $delta = 0.0, ?string $refType = null): array
{
    $t = strtolower(trim($type));
    $ref = strtolower(trim((string)$refType));

    if ($t === 'initial' || ($t === 'manual_adjustment' && $delta > 0 && $ref === 'manual')) {
        return ['label' => 'إضافة مخزون مركزي', 'badge' => 'success', 'icon' => '📥', 'is_in' => true];
    }
    if ($t === 'transfer_in' || ($ref === 'transfer' && $delta > 0)) {
        return ['label' => 'توريد وارد للفرع', 'badge' => 'info', 'icon' => '🚚', 'is_in' => true];
    }
    if ($t === 'transfer_out' || ($ref === 'transfer' && $delta < 0)) {
        return ['label' => 'توريد صادر من المخزن', 'badge' => 'warning', 'icon' => '📤', 'is_in' => false];
    }
    if ($t === 'sale' || $ref === 'invoice') {
        return ['label' => 'بيع فاتورة', 'badge' => 'purple', 'icon' => '🛒', 'is_in' => false];
    }
    if ($t === 'return' || $t === 'return_future' || $ref === 'return') {
        return ['label' => 'مرتجع بيع', 'badge' => 'success', 'icon' => '↩️', 'is_in' => true];
    }
    if ($ref === 'waste' || str_contains($t, 'waste')) {
        return ['label' => 'هالك وتالف', 'badge' => 'danger', 'icon' => '🗑️', 'is_in' => false];
    }
    if ($delta > 0) {
        return ['label' => 'تسوية جردية بالزيادة', 'badge' => 'info', 'icon' => '➕', 'is_in' => true];
    }
    return ['label' => 'تسوية جردية بالخصم', 'badge' => 'danger', 'icon' => '➖', 'is_in' => false];
}


